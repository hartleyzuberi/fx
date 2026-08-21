<?php

namespace App\AI\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RuntimeModeResolver
{
    public function __construct(private ProviderRegistry $registry, private AiFeature $features) {}

    public function resolve(?User $user = null): string
    {
        $rag = $this->features->enabled('RAG_ENABLED', (bool) config('ai.rag_enabled')) && $this->retrievalAvailable();
        if (! $this->features->enabled('AI_TUTOR_ENABLED', (bool) config('ai.enabled')) || $this->features->enabled('AI_MAINTENANCE_MODE', (bool) config('ai.maintenance_mode')) || ($user?->learnerProfile && ! $user->learnerProfile->ai_tutor_enabled)) {
            return $rag ? 'RETRIEVAL_ONLY' : 'NO_AI';
        }
        $available = collect($this->registry->all())->filter(fn (array $provider): bool => $provider['enabled'] && $provider['configured']);
        $local = $available->contains(fn (array $provider): bool => $provider['cost_class'] === 'local' && (! $user || $user->learnerProfile?->allow_local_ai));
        $cloud = $available->contains(fn (array $provider): bool => $provider['cost_class'] !== 'local' && (! $user || $user->learnerProfile?->allow_cloud_ai));
        if ($local && $cloud) {
            return 'HYBRID_AI';
        }
        if ($local) {
            return 'LOCAL_AI';
        }
        if ($cloud) {
            return $this->features->enabled('AI_PAID_FALLBACK_ALLOWED', (bool) config('ai.paid_fallback_allowed')) ? 'FULL_AI' : 'FREE_AI';
        }

        return $rag ? 'RETRIEVAL_ONLY' : 'NO_AI';
    }

    private function retrievalAvailable(): bool
    {
        return Schema::hasTable('source_segments') && DB::table('source_segments')->exists();
    }
}
