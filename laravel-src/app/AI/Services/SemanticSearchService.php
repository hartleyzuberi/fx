<?php

namespace App\AI\Services;

use App\AI\Contracts\EmbeddingProviderInterface;
use App\AI\Exceptions\ProviderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SemanticSearchService
{
    public function __construct(private ProviderRegistry $registry, private AiFeature $features) {}

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, float>
     */
    public function scores(string $query, array $candidates): array
    {
        if (! $this->features->enabled('SEMANTIC_SEARCH_ENABLED', (bool) config('ai.semantic_search_enabled')) || ! Schema::hasTable('ai_embedding_records')) {
            return [];
        }
        $providerKey = (string) config('ai.embedding_provider');
        $descriptor = $this->registry->descriptor($providerKey);
        if (! $descriptor || ! $descriptor['enabled'] || ! $descriptor['embedding_model']) {
            return [];
        }
        if ($descriptor['cost_class'] === 'paid' && ! $this->features->enabled('AI_PAID_FALLBACK_ALLOWED', (bool) config('ai.paid_fallback_allowed'))) {
            return [];
        }
        $provider = $this->registry->provider($providerKey);
        if (! $provider instanceof EmbeddingProviderInterface) {
            return [];
        }
        try {
            $queryVector = $provider->embed([$query], (string) $descriptor['embedding_model'])[0] ?? null;
        } catch (ProviderException) {
            return [];
        }
        if (! is_array($queryVector) || $queryVector === []) {
            return [];
        }
        $records = DB::table('ai_embedding_records')->where('provider', $providerKey)
            ->where('model', $descriptor['embedding_model'])->whereIn('source_segment_id', collect($candidates)->pluck('id'))
            ->get(['source_segment_id', 'embedding']);

        return $records->mapWithKeys(function ($record) use ($queryVector): array {
            $decoded = json_decode($record->embedding, true);
            $vector = is_array($decoded) ? array_values(array_map('floatval', $decoded)) : null;

            return [$record->source_segment_id => is_array($vector) ? $this->cosine($queryVector, $vector) : 0.0];
        })->all();
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === []) {
            return 0.0;
        }
        $dot = $normA = $normB = 0.0;
        foreach ($a as $index => $value) {
            $other = (float) $b[$index];
            $dot += (float) $value * $other;
            $normA += (float) $value ** 2;
            $normB += $other ** 2;
        }

        return $normA > 0 && $normB > 0 ? $dot / (sqrt($normA) * sqrt($normB)) : 0.0;
    }
}
