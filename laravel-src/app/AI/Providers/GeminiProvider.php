<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\Data\AiRequest;
use App\AI\Data\AiResponse;
use App\AI\Exceptions\ProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface, EmbeddingProviderInterface
{
    public function key(): string
    {
        return 'gemini';
    }

    public function generate(AiRequest $request, string $model): AiResponse
    {
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $request->systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $request->prompt]]]],
            'generationConfig' => ['temperature' => $request->task === 'semantic_assessment' ? 0 : 0.2],
        ];
        if ($request->schema !== null) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
            $payload['generationConfig']['responseJsonSchema'] = $request->schema;
        }

        try {
            $response = $this->http()->post($this->url("models/{$model}:generateContent"), $payload);
        } catch (\Throwable $exception) {
            throw new ProviderException('Gemini connection failed.', 'connection_error', true, previous: $exception);
        }
        $this->guard($response);
        $json = $response->json();
        $text = data_get($json, 'candidates.0.content.parts.0.text');
        if (! is_string($text) || trim($text) === '') {
            throw new ProviderException('Gemini returned no usable text.', 'empty_response', true);
        }

        return new AiResponse(trim($text), $this->key(), $model, null, [
            'input_tokens' => (int) data_get($json, 'usageMetadata.promptTokenCount', 0),
            'output_tokens' => (int) data_get($json, 'usageMetadata.candidatesTokenCount', 0),
        ]);
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, string $model): array
    {
        $vectors = [];
        foreach ($texts as $text) {
            try {
                $response = $this->http()->post($this->url("models/{$model}:embedContent"), [
                    'model' => "models/{$model}",
                    'content' => ['parts' => [['text' => $text]]],
                    'taskType' => 'RETRIEVAL_DOCUMENT',
                ]);
            } catch (\Throwable $exception) {
                throw new ProviderException('Gemini embedding connection failed.', 'connection_error', true, previous: $exception);
            }
            $this->guard($response);
            $vector = $response->json('embedding.values');
            if (! is_array($vector)) {
                throw new ProviderException('Gemini returned no embedding.', 'empty_response', true);
            }
            $vectors[] = array_values(array_map('floatval', $vector));
        }

        return $vectors;
    }

    public function test(string $model): array
    {
        $started = microtime(true);
        try {
            $reply = $this->generate(new AiRequest('health_check', 'text_generation', 'Reply with exactly OK.', 'OK', 'health_v1'), $model);

            return ['ok' => true, 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'model' => $reply->model, 'error_code' => null, 'message' => 'Connected; minimal inference succeeded.'];
        } catch (ProviderException $exception) {
            return ['ok' => false, 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'model' => $model ?: null, 'error_code' => $exception->errorCode, 'message' => $exception->getMessage()];
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout((int) config('ai.connect_timeout_seconds'))->timeout((int) config('ai.timeout_seconds'));
    }

    private function url(string $path): string
    {
        return rtrim((string) config('ai.providers.gemini.base_url'), '/').'/'.$path.'?key='.rawurlencode((string) config('ai.providers.gemini.api_key'));
    }

    private function guard(Response $response): void
    {
        if ($response->successful()) {
            return;
        }
        $status = $response->status();
        $code = match ($status) {
            401, 403 => 'authentication_failed', 404 => 'model_not_found', 408 => 'timeout', 429 => 'rate_limited',
            500, 502, 503, 504 => 'provider_unavailable', default => 'provider_rejected',
        };
        throw new ProviderException('Gemini request failed.', $code, in_array($status, [408, 429, 500, 502, 503, 504], true), $status);
    }
}
