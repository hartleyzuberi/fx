<?php

namespace App\Http\Controllers\Admin;

use App\AI\Services\AiFeature;
use App\AI\Services\CircuitBreaker;
use App\AI\Services\ProviderRegistry;
use App\AI\Services\RuntimeModeResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiOperationsController extends Controller
{
    public function index(ProviderRegistry $registry, CircuitBreaker $circuit, RuntimeModeResolver $modes, AiFeature $features): Response
    {
        $providers = collect($registry->all())->map(fn (array $provider): array => [...$provider,
            'health' => ! $provider['enabled'] ? 'disabled' : (! $provider['configured'] ? 'not_configured' : $circuit->status($provider['key'], 'text_generation'))]);
        $usage = DB::table('ai_usage_events')->select(['provider', 'model', 'task_type'])->selectRaw('count(*) as requests, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(case when status = \'failed\' then 1 else 0 end) as failures, sum(case when fallback_used = 1 then 1 else 0 end) as fallbacks, avg(latency_ms) as average_latency_ms, sum(estimated_cost_usd) as estimated_cost_usd')->groupBy('provider', 'model', 'task_type')->latest('requests')->limit(100)->get();
        $flags = ['AI_TUTOR_ENABLED', 'AI_SEMANTIC_GRADING_ENABLED', 'AI_VISION_ENABLED', 'AI_EXTERNAL_RETRIEVAL_ENABLED', 'AI_SUPPLEMENTAL_KNOWLEDGE_ENABLED', 'RAG_ENABLED', 'SEMANTIC_SEARCH_ENABLED', 'AI_MAINTENANCE_MODE', 'AI_PAID_FALLBACK_ALLOWED'];
        $storedFlags = DB::table('feature_flags')->whereIn('key', $flags)->get()->keyBy('key');
        $storedRouting = Schema::hasTable('ai_routing_rules') ? DB::table('ai_routing_rules')->get()->keyBy('task_type') : collect();
        $routing = collect(['tutoring', 'semantic_assessment', 'classification', 'vision'])->map(function (string $task) use ($storedRouting): array {
            $rule = $storedRouting->get($task);
            if (! $rule) {
                return ['task_type' => $task, 'primary_provider' => null, 'fallback_providers' => '[]', 'local_first' => false, 'enabled' => true];
            }

            return ['task_type' => $task, 'primary_provider' => $rule->primary_provider, 'fallback_providers' => $rule->fallback_providers ?: '[]', 'local_first' => (bool) $rule->local_first, 'enabled' => (bool) $rule->enabled];
        });

        return Inertia::render('admin/ai-operations', [
            'runtimeMode' => $modes->resolve(), 'paidFallbackAllowed' => $features->enabled('AI_PAID_FALLBACK_ALLOWED', (bool) config('ai.paid_fallback_allowed')), 'providers' => $providers,
            'routing' => $routing,
            'usage' => $usage, 'indexState' => Schema::hasTable('ai_index_states') ? DB::table('ai_index_states')->where('key', 'course')->first() : null,
            'flags' => collect($flags)->map(function (string $key) use ($storedFlags): array {
                $stored = $storedFlags->get($key);

                return ['key' => $key, 'enabled' => $stored ? (bool) $stored->enabled : $this->defaultFlag($key)];
            }),
        ]);
    }

    public function updateProvider(Request $request, string $provider, ProviderRegistry $registry): RedirectResponse
    {
        abort_unless($registry->descriptor($provider) !== null, 404);
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'model' => ['nullable', 'string', 'max:200'], 'embedding_model' => ['nullable', 'string', 'max:200'],
            'priority' => ['required', 'integer', 'between:1,1000'], 'cost_class' => ['required', Rule::in(['local', 'free', 'paid'])],
            'daily_budget_usd' => ['nullable', 'numeric', 'min:0'], 'monthly_budget_usd' => ['nullable', 'numeric', 'min:0']]);
        $before = DB::table('ai_provider_settings')->where('provider', $provider)->first();
        DB::table('ai_provider_settings')->updateOrInsert(['provider' => $provider], [...$data, 'id' => $before ? $before->id : (string) Str::ulid(), 'updated_by' => $request->user()->id, 'created_at' => $before ? $before->created_at : now(), 'updated_at' => now()]);
        $this->audit($request, 'ai_provider_updated', 'ai_provider', $provider, $before ? (array) $before : null, $data);

        return back()->with('success', ucfirst($provider).' provider settings updated.');
    }

    public function updateRouting(Request $request, string $task, ProviderRegistry $registry): RedirectResponse
    {
        $providerKeys = collect($registry->all())->pluck('key')->all();
        $data = $request->validate(['primary_provider' => ['nullable', Rule::in($providerKeys)], 'fallback_providers' => ['nullable', 'array', 'max:4'],
            'fallback_providers.*' => ['nullable', Rule::in($providerKeys)], 'local_first' => ['required', 'boolean'], 'enabled' => ['required', 'boolean']]);
        $before = DB::table('ai_routing_rules')->where('task_type', $task)->first();
        DB::table('ai_routing_rules')->updateOrInsert(['task_type' => $task], ['id' => $before ? $before->id : (string) Str::ulid(), 'primary_provider' => $data['primary_provider'] ?? null,
            'fallback_providers' => json_encode(array_values(array_unique(array_filter($data['fallback_providers'] ?? [], 'is_string'))), JSON_THROW_ON_ERROR), 'local_first' => $data['local_first'],
            'enabled' => $data['enabled'], 'updated_by' => $request->user()->id, 'created_at' => $before ? $before->created_at : now(), 'updated_at' => now()]);
        $this->audit($request, 'ai_routing_updated', 'ai_routing_rule', $task, $before ? (array) $before : null, $data);

        return back()->with('success', 'Routing rule updated.');
    }

    public function testProvider(Request $request, string $provider, ProviderRegistry $registry): RedirectResponse
    {
        $descriptor = $registry->descriptor($provider);
        abort_unless($descriptor !== null, 404);
        if (! $descriptor['configured']) {
            return back()->withErrors(['provider' => ucfirst($provider).' is not configured with a server-side key/model.']);
        }
        $result = $registry->provider($provider)->test((string) $descriptor['model']);

        return back()->with($result['ok'] ? 'success' : 'error', ucfirst($provider).': '.$result['message'].' ('.$result['latency_ms'].' ms)');
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(Request $request, string $event, string $type, string $id, ?array $before, array $after): void
    {
        DB::table('admin_audit_events')->insert(['id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'event_type' => $event, 'subject_type' => $type,
            'subject_id' => $id, 'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null, 'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function defaultFlag(string $key): bool
    {
        return match ($key) {
            'AI_TUTOR_ENABLED' => (bool) config('ai.enabled'), 'AI_SEMANTIC_GRADING_ENABLED' => (bool) config('ai.semantic_grading_enabled'),
            'AI_VISION_ENABLED' => (bool) config('ai.vision_enabled'), 'AI_EXTERNAL_RETRIEVAL_ENABLED' => (bool) config('ai.external_retrieval_enabled'),
            'AI_SUPPLEMENTAL_KNOWLEDGE_ENABLED' => (bool) config('ai.supplemental_knowledge_enabled'), 'RAG_ENABLED' => (bool) config('ai.rag_enabled'),
            'AI_PAID_FALLBACK_ALLOWED' => (bool) config('ai.paid_fallback_allowed'),
            'SEMANTIC_SEARCH_ENABLED' => (bool) config('ai.semantic_search_enabled'), 'AI_MAINTENANCE_MODE' => (bool) config('ai.maintenance_mode'), default => false,
        };
    }
}
