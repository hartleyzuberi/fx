<?php

namespace App\AI\Services;

use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\Exceptions\ProviderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmbeddingIndexService
{
    public function __construct(private ProviderRegistry $registry, private AiFeature $features) {}

    /**
     * @param  (callable(int, int, int): void)|null  $progress
     * @return array{state: string, indexed: int, unchanged: int, failed: int, provider: string, model: string}
     */
    public function reindex(?callable $progress = null): array
    {
        $providerKey = (string) config('ai.embedding_provider');
        $descriptor = $this->registry->descriptor($providerKey);
        if (! $descriptor || ! $descriptor['enabled'] || ! $descriptor['embedding_model']) {
            throw new ProviderException('Embedding provider or model is not configured.', 'not_configured', false);
        }
        if ($descriptor['cost_class'] === 'paid' && ! $this->features->enabled('AI_PAID_FALLBACK_ALLOWED', (bool) config('ai.paid_fallback_allowed'))) {
            throw new ProviderException('Paid embeddings are prohibited by policy.', 'paid_fallback_prohibited', false);
        }
        $provider = $this->registry->provider($providerKey);
        if (! $provider instanceof EmbeddingProviderInterface) {
            throw new ProviderException('Configured provider does not support embeddings.', 'capability_mismatch', false);
        }
        $model = (string) $descriptor['embedding_model'];
        $this->state('building', $providerKey, $model);
        $indexed = $unchanged = $failed = 0;

        try {
            DB::table('source_segments')->orderBy('id')->select(['id', 'content', 'content_sha256'])->chunk(16, function ($segments) use ($provider, $providerKey, $model, &$indexed, &$unchanged, &$failed, $progress): void {
                $pending = $segments->filter(function ($segment) use ($providerKey, $model, &$unchanged): bool {
                    $same = DB::table('ai_embedding_records')->where('source_segment_id', $segment->id)->where('provider', $providerKey)
                        ->where('model', $model)->where('content_sha256', $segment->content_sha256)->exists();
                    if ($same) {
                        $unchanged++;
                    }

                    return ! $same;
                })->values();
                if ($pending->isEmpty()) {
                    return;
                }
                try {
                    $texts = array_values(array_map(fn (object $segment): string => (string) $segment->content, $pending->all()));
                    $vectors = $provider->embed($texts, $model);
                    foreach ($pending as $index => $segment) {
                        $vector = $vectors[$index] ?? null;
                        if (! is_array($vector) || $vector === []) {
                            $failed++;

                            continue;
                        }
                        $existing = DB::table('ai_embedding_records')->where('source_segment_id', $segment->id)->where('provider', $providerKey)->where('model', $model)->first();
                        DB::table('ai_embedding_records')->updateOrInsert(
                            ['source_segment_id' => $segment->id, 'provider' => $providerKey, 'model' => $model],
                            ['id' => $existing ? $existing->id : (string) Str::ulid(), 'content_sha256' => $segment->content_sha256, 'dimensions' => count($vector),
                                'embedding' => json_encode($vector, JSON_THROW_ON_ERROR), 'indexed_at' => now(), 'created_at' => $existing ? $existing->created_at : now(), 'updated_at' => now()],
                        );
                        $indexed++;
                    }
                    $progress && $progress($indexed, $unchanged, $failed);
                } catch (ProviderException $exception) {
                    $failed += $pending->count();
                    throw $exception;
                }
            });
            $state = $failed > 0 ? 'stale' : 'ready';
            $this->state($state, $providerKey, $model, null, $indexed + $unchanged);

            return ['state' => $state, 'indexed' => $indexed, 'unchanged' => $unchanged, 'failed' => $failed, 'provider' => $providerKey, 'model' => $model];
        } catch (\Throwable $exception) {
            $this->state('failed', $providerKey, $model, $exception->getMessage());
            throw $exception;
        }
    }

    private function state(string $state, string $provider, string $model, ?string $error = null, int $count = 0): void
    {
        $existing = DB::table('ai_index_states')->where('key', 'course')->first();
        DB::table('ai_index_states')->updateOrInsert(['key' => 'course'], [
            'id' => $existing ? $existing->id : (string) Str::ulid(), 'state' => $state, 'provider' => $provider, 'model' => $model,
            'record_count' => $count, 'last_error' => $error, 'started_at' => $state === 'building' ? now() : ($existing ? $existing->started_at : null),
            'completed_at' => in_array($state, ['ready', 'stale'], true) ? now() : null,
            'created_at' => $existing ? $existing->created_at : now(), 'updated_at' => now(),
        ]);
    }
}
