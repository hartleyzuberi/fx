<?php

namespace App\AI\Services;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function allows(string $provider, string $capability): bool
    {
        $state = Cache::get($this->key($provider, $capability), ['failures' => 0, 'opened_until' => null]);

        return empty($state['opened_until']) || now()->timestamp >= (int) $state['opened_until'];
    }

    public function succeed(string $provider, string $capability): void
    {
        Cache::forget($this->key($provider, $capability));
    }

    public function fail(string $provider, string $capability): void
    {
        $key = $this->key($provider, $capability);
        $state = Cache::get($key, ['failures' => 0, 'opened_until' => null]);
        $failures = (int) $state['failures'] + 1;
        $threshold = max(1, (int) config('ai.circuit_failure_threshold'));
        Cache::put($key, [
            'failures' => $failures,
            'opened_until' => $failures >= $threshold ? now()->addSeconds((int) config('ai.circuit_cooldown_seconds'))->timestamp : null,
        ], now()->addSeconds(max(60, (int) config('ai.circuit_cooldown_seconds') * 2)));
    }

    public function status(string $provider, string $capability): string
    {
        return $this->allows($provider, $capability) ? 'available' : 'degraded';
    }

    private function key(string $provider, string $capability): string
    {
        return "ai:circuit:{$provider}:{$capability}";
    }
}
