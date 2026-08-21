<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Data\AiRequest;
use App\AI\Data\AiResponse;
use App\AI\Exceptions\ProviderException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AbstractOpenAiCompatibleProvider implements AiProviderInterface
{
    abstract public function key(): string;

    public function generate(AiRequest $request, string $model): AiResponse
    {
        $settings = config("ai.providers.{$this->key()}");
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $request->prompt],
            ],
            'temperature' => $request->task === 'semantic_assessment' ? 0 : 0.2,
        ];
        if ($request->schema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'assessment_feedback', 'strict' => true, 'schema' => $request->schema],
            ];
        }

        $http = Http::withToken((string) $settings['api_key'])
            ->acceptJson()
            ->connectTimeout((int) config('ai.connect_timeout_seconds'))
            ->timeout((int) config('ai.timeout_seconds'));
        if ($this->key() === 'openrouter') {
            $http = $http->withHeaders(['HTTP-Referer' => (string) config('app.url'), 'X-Title' => (string) config('app.name')]);
        }

        try {
            $response = $http->post(rtrim((string) $settings['base_url'], '/').'/chat/completions', $payload);
        } catch (\Throwable $exception) {
            throw new ProviderException('Provider connection failed.', 'connection_error', true, previous: $exception);
        }
        $this->guardResponse($response);
        $json = $response->json();
        $text = data_get($json, 'choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new ProviderException('Provider returned no usable text.', 'empty_response', true);
        }

        return new AiResponse(
            trim($text),
            $this->key(),
            (string) ($json['model'] ?? $model),
            isset($json['id']) ? (string) $json['id'] : null,
            ['input_tokens' => (int) data_get($json, 'usage.prompt_tokens', 0), 'output_tokens' => (int) data_get($json, 'usage.completion_tokens', 0)],
        );
    }

    public function test(string $model): array
    {
        $started = microtime(true);
        try {
            $response = $this->generate(new AiRequest('health_check', 'text_generation', 'Reply with exactly OK.', 'OK', 'health_v1'), $model);

            return ['ok' => true, 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'model' => $response->model, 'error_code' => null, 'message' => 'Connected; minimal inference succeeded.'];
        } catch (ProviderException $exception) {
            return ['ok' => false, 'latency_ms' => (int) ((microtime(true) - $started) * 1000), 'model' => $model ?: null, 'error_code' => $exception->errorCode, 'message' => $exception->getMessage()];
        }
    }

    protected function guardResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }
        $status = $response->status();
        $code = match ($status) {
            401, 403 => 'authentication_failed',
            404 => 'model_not_found',
            408 => 'timeout',
            429 => 'rate_limited',
            500, 502, 503, 504 => 'provider_unavailable',
            default => 'provider_rejected',
        };
        $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true);
        throw new ProviderException('Provider request failed.', $code, $retryable, $status);
    }
}
