<?php

namespace App\AI\Services;

use App\AI\Data\AiRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiRouter
{
    public function __construct(private ProviderRegistry $registry, private CircuitBreaker $circuit, private AiFeature $features) {}

    /** @return list<array<string, mixed>> */
    public function candidates(AiRequest $request, ?User $user = null): array
    {
        if (! $this->features->enabled('AI_TUTOR_ENABLED', (bool) config('ai.enabled')) || $this->features->enabled('AI_MAINTENANCE_MODE', (bool) config('ai.maintenance_mode'))) {
            return [];
        }

        $profile = $user?->learnerProfile;
        if ($profile && ! $profile->ai_tutor_enabled) {
            return [];
        }
        $allowCloud = ! $profile || (bool) $profile->allow_cloud_ai;
        $allowLocal = ! $profile || (bool) $profile->allow_local_ai;
        $paidAllowed = $this->features->enabled('AI_PAID_FALLBACK_ALLOWED', (bool) config('ai.paid_fallback_allowed'));
        $rule = $this->rule($request->task);
        $fallbacks = $rule && is_string($rule['fallback_providers'] ?? null) ? json_decode($rule['fallback_providers'], true) : [];
        $fallbacks = is_array($fallbacks) ? array_values(array_filter($fallbacks, 'is_string')) : [];
        $configuredOrder = config('ai.routing', []);
        $configuredOrder = is_array($configuredOrder) ? array_values(array_filter($configuredOrder, 'is_string')) : [];
        $order = $rule ? array_values(array_filter([(is_string($rule['primary_provider'] ?? null) ? $rule['primary_provider'] : null), ...$fallbacks], 'is_string')) : $configuredOrder;
        $providers = $this->registry->all();
        usort($providers, function (array $left, array $right) use ($order): int {
            $leftPosition = array_search($left['key'], $order, true);
            $rightPosition = array_search($right['key'], $order, true);
            $leftRank = $leftPosition === false ? 1000 + (int) $left['priority'] : $leftPosition;
            $rightRank = $rightPosition === false ? 1000 + (int) $right['priority'] : $rightPosition;

            return $leftRank <=> $rightRank;
        });
        if (($rule && (bool) ($rule['local_first'] ?? false)) || config('ai.local_first')) {
            usort($providers, fn (array $left, array $right): int => ($left['cost_class'] === 'local' ? 0 : 1) <=> ($right['cost_class'] === 'local' ? 0 : 1));
        }

        return array_values(array_filter($providers, function (array $provider) use ($request, $allowCloud, $allowLocal, $paidAllowed): bool {
            $local = $provider['cost_class'] === 'local';
            if (! $provider['enabled'] || ! $provider['configured'] || ! in_array($request->capability, $provider['capabilities'], true)) {
                return false;
            }
            if (($local && ! $allowLocal) || (! $local && ! $allowCloud)) {
                return false;
            }
            if ($provider['cost_class'] === 'paid' && ! $paidAllowed) {
                return false;
            }
            if ($request->sensitive && ! $local && ! $provider['recommended_for_private_data']) {
                return false;
            }
            if (! $this->circuit->allows($provider['key'], $request->capability)) {
                return false;
            }

            return ! $this->budgetExceeded($provider);
        }));
    }

    /** @return array<string, mixed>|null */
    private function rule(string $task): ?array
    {
        if (! Schema::hasTable('ai_routing_rules')) {
            return null;
        }

        $rule = DB::table('ai_routing_rules')->where('task_type', $task)->where('enabled', true)->first();

        return $rule ? (array) $rule : null;
    }

    /** @param array<string, mixed> $provider */
    private function budgetExceeded(array $provider): bool
    {
        if (! Schema::hasTable('ai_usage_events')) {
            return false;
        }
        $dailyLimit = (float) ($provider['daily_budget_usd'] ?? config('ai.daily_budget_usd'));
        $monthlyLimit = (float) ($provider['monthly_budget_usd'] ?? config('ai.monthly_budget_usd'));
        if ($dailyLimit > 0 && (float) DB::table('ai_usage_events')->where('provider', $provider['key'])->where('occurred_at', '>=', now()->startOfDay())->sum('estimated_cost_usd') >= $dailyLimit) {
            return true;
        }

        return $monthlyLimit > 0 && (float) DB::table('ai_usage_events')->where('provider', $provider['key'])->where('occurred_at', '>=', now()->startOfMonth())->sum('estimated_cost_usd') >= $monthlyLimit;
    }
}
