<?php

namespace App\AI\Providers;

use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\Exceptions\ProviderException;
use Illuminate\Support\Facades\Http;

class OpenAiProvider extends AbstractOpenAiCompatibleProvider implements EmbeddingProviderInterface
{
    public function key(): string
    {
        return 'openai';
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, string $model): array
    {
        try {
            $response = Http::withToken((string) config('ai.providers.openai.api_key'))->acceptJson()
                ->connectTimeout((int) config('ai.connect_timeout_seconds'))->timeout((int) config('ai.timeout_seconds'))
                ->post(rtrim((string) config('ai.providers.openai.base_url'), '/').'/embeddings', ['model' => $model, 'input' => $texts]);
        } catch (\Throwable $exception) {
            throw new ProviderException('Embedding connection failed.', 'connection_error', true, previous: $exception);
        }
        $this->guardResponse($response);

        $data = $response->json('data', []);
        if (! is_array($data)) {
            throw new ProviderException('OpenAI returned no embeddings.', 'empty_response', true);
        }
        usort($data, fn (array $left, array $right): int => ((int) ($left['index'] ?? 0)) <=> ((int) ($right['index'] ?? 0)));

        return array_map(fn (array $item): array => array_values(array_map('floatval', is_array($item['embedding'] ?? null) ? $item['embedding'] : [])), $data);
    }
}
