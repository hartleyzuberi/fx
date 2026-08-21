<?php

namespace App\AI\Services;

use App\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProviderRegistry
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $overrides = [];
        if (Schema::hasTable('ai_provider_settings')) {
            foreach (DB::table('ai_provider_settings')->get() as $row) {
                $data = (array) $row;
                $overrides[(string) $data['provider']] = $data;
            }
        }
        $providers = config('ai.providers', []);
        if (! is_array($providers)) {
            return [];
        }
        $routing = config('ai.routing', []);
        $routing = is_array($routing) ? array_values($routing) : [];
        $result = [];
        foreach ($providers as $key => $settings) {
            if (! is_string($key) || ! is_array($settings)) {
                continue;
            }
            $override = $overrides[$key] ?? null;
            $decodedCapabilities = isset($override['capabilities']) && is_string($override['capabilities']) ? json_decode($override['capabilities'], true) : null;
            $capabilities = is_array($decodedCapabilities) ? $decodedCapabilities : ($settings['capabilities'] ?? []);
            $model = ! empty($override['model']) ? $override['model'] : ($settings['model'] ?? null);
            $embeddingModel = ! empty($override['embedding_model']) ? $override['embedding_model'] : ($settings['embedding_model'] ?? null);
            $enabled = array_key_exists('enabled', $override ?? []) && $override['enabled'] !== null ? (bool) $override['enabled'] : (bool) ($settings['enabled'] ?? false);
            $costClass = ! empty($override['cost_class']) ? $override['cost_class'] : ($settings['cost_class'] ?? 'paid');
            $configured = $key === 'ollama'
                ? ($model !== null && $model !== '')
                : (($settings['api_key'] ?? null) !== null && $settings['api_key'] !== '' && $model !== null && $model !== '');
            $position = array_search($key, $routing, true);

            $result[] = [
                'key' => $key,
                'enabled' => $enabled,
                'configured' => $configured,
                'model' => $model,
                'embedding_model' => $embeddingModel,
                'priority' => isset($override['priority']) ? (int) $override['priority'] : ($position === false ? 100 : $position + 1),
                'cost_class' => $costClass,
                'capabilities' => array_values($capabilities ?: []),
                'privacy_policy_url' => (string) ($settings['privacy_policy_url'] ?? ''),
                'data_retention_notes' => (string) ($settings['data_retention_notes'] ?? ''),
                'recommended_for_private_data' => (bool) ($settings['recommended_for_private_data'] ?? false),
                'last_verified' => (string) ($settings['last_verified'] ?? ''),
                'last_success_at' => $override['last_success_at'] ?? null,
                'last_failure_at' => $override['last_failure_at'] ?? null,
                'last_error_code' => $override['last_error_code'] ?? null,
                'last_error' => $override['last_error'] ?? null,
                'daily_budget_usd' => $override['daily_budget_usd'] ?? null,
                'monthly_budget_usd' => $override['monthly_budget_usd'] ?? null,
            ];
        }
        usort($result, fn (array $left, array $right): int => ((int) $left['priority']) <=> ((int) $right['priority']));

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function descriptor(string $key): ?array
    {
        foreach ($this->all() as $provider) {
            if ($provider['key'] === $key) {
                return $provider;
            }
        }

        return null;
    }

    public function provider(string $key): AiProviderInterface
    {
        $class = config("ai.providers.{$key}.adapter");
        abort_unless(is_string($class) && is_a($class, AiProviderInterface::class, true), 500, 'AI provider adapter is invalid.');

        return app($class);
    }
}
