<?php

namespace App\AI\Services;

use App\AI\Data\AiRequest;
use App\AI\Data\AiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiUsageService
{
    public function success(AiRequest $request, AiResponse $response, int $latencyMs, bool $fallback): void
    {
        $this->record($request, $response->provider, $response->model, 'success', $latencyMs, $fallback, null, $response->usage);
        $this->providerHealth($response->provider, true);
    }

    public function failure(AiRequest $request, string $provider, string $model, int $latencyMs, bool $fallback, string $errorCode, string $message): void
    {
        $this->record($request, $provider, $model, 'failed', $latencyMs, $fallback, $errorCode, []);
        $this->providerHealth($provider, false, $errorCode, $message);
    }

    /** @param array<string, int> $usage */
    private function record(AiRequest $request, string $provider, string $model, string $status, int $latencyMs, bool $fallback, ?string $errorCode, array $usage): void
    {
        if (! Schema::hasTable('ai_usage_events')) {
            return;
        }
        DB::table('ai_usage_events')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $request->userId, 'feature' => $request->task,
            'provider' => $provider, 'task_type' => $request->task, 'capability' => $request->capability, 'model' => $model,
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0), 'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'estimated_cost_usd' => 0, 'status' => $status, 'latency_ms' => $latencyMs, 'fallback_used' => $fallback,
            'error_code' => $errorCode, 'prompt_version' => $request->promptVersion, 'request_id' => $request->requestId,
            'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function providerHealth(string $provider, bool $success, ?string $errorCode = null, ?string $message = null): void
    {
        if (! Schema::hasTable('ai_provider_settings')) {
            return;
        }
        $existing = DB::table('ai_provider_settings')->where('provider', $provider)->first();
        $base = ['updated_at' => now()];
        if (! $existing) {
            $base += ['id' => (string) Str::ulid(), 'provider' => $provider, 'priority' => 100, 'created_at' => now()];
        }
        $health = $success
            ? ['last_success_at' => now(), 'last_error_code' => null, 'last_error' => null]
            : ['last_failure_at' => now(), 'last_error_code' => $errorCode, 'last_error' => Str::limit((string) $message, 1000)];
        DB::table('ai_provider_settings')->updateOrInsert(['provider' => $provider], $base + $health);
    }
}
