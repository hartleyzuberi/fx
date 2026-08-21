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

class OllamaProvider implements AiProviderInterface, EmbeddingProviderInterface
{
    public function key(): string
    {
        return 'ollama';
    }

    public function generate(AiRequest $request, string $model): AiResponse
    {
        $payload = [
            'model' => $model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $request->prompt],
            ],
            'options' => ['temperature' => $request->task === 'semantic_assessment' ? 0 : 0.2],
        ];
        if ($request->schema !== null) {
            $payload['format'] = $request->schema;
        }
        try {
            $response = $this->http()->post($this->url('/api/chat'), $payload);
        } catch (\Throwable $exception) {
            throw new ProviderException('Local Ollama is unavailable.', 'connection_error', true, previous: $exception);
        }
        $this->guard($response);
        $json = $response->json();
        $text = data_get($json, 'message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new ProviderException('Ollama returned no usable text.', 'empty_response', true);
        }

        return new AiResponse(trim($text), $this->key(), (string) ($json['model'] ?? $model), null, [
            'input_tokens' => (int) ($json['prompt_eval_count'] ?? 0),
            'output_tokens' => (int) ($json['eval_count'] ?? 0),
        ]);
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, string $model): array
    {
        try {
            $response = $this->http()->post($this->url('/api/embed'), ['model' => $model, 'input' => $texts, 'truncate' => true]);
        } catch (\Throwable $exception) {
            throw new ProviderException('Ollama embeddings are unavailable.', 'connection_error', true, previous: $exception);
        }
        $this->guard($response);
        $vectors = $response->json('embeddings');
        if (! is_array($vectors) || count($vectors) !== count($texts)) {
            throw new ProviderException('Ollama returned incomplete embeddings.', 'empty_response', true);
        }

        return array_values(array_map(fn (array $vector): array => array_values(array_map('floatval', $vector)), $vectors));
    }

    public function test(string $model): array
    {
        $started = microtime(true);
        try {
            $reply = $this->generate(new AiRequest('health_check', 'text_generation', 'Reply with exactly OK.', 'OK', 'health_v1'), $model);

            return ['ok' => true, 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'model' => $reply->model, 'error_code' => null, 'message' => 'Connected; local inference succeeded.'];
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
        return rtrim((string) config('ai.providers.ollama.base_url'), '/').$path;
    }

    private function guard(Response $response): void
    {
        if ($response->successful()) {
            return;
        }
        $status = $response->status();
        $code = $status === 404 ? 'model_not_found' : ($status === 429 ? 'rate_limited' : ($status >= 500 ? 'provider_unavailable' : 'provider_rejected'));
        throw new ProviderException('Ollama request failed.', $code, $status === 429 || $status >= 500, $status);
    }
}
