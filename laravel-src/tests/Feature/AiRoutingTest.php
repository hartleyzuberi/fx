<?php

namespace Tests\Feature;

use App\AI\Data\AiRequest;
use App\AI\Services\AiManager;
use App\Exceptions\TutorUnavailableException;
use App\Models\LearnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'ai.enabled' => true, 'ai.paid_fallback_allowed' => false, 'ai.routing' => ['groq', 'openrouter', 'openai'],
            'ai.max_requests_per_user_per_day' => 100,
            'ai.providers.groq.enabled' => true, 'ai.providers.groq.api_key' => 'server-secret', 'ai.providers.groq.model' => 'free-model', 'ai.providers.groq.cost_class' => 'free',
            'ai.providers.openrouter.enabled' => true, 'ai.providers.openrouter.api_key' => 'server-secret-2', 'ai.providers.openrouter.model' => 'openrouter/free', 'ai.providers.openrouter.cost_class' => 'free',
            'ai.providers.openai.enabled' => true, 'ai.providers.openai.api_key' => 'paid-secret', 'ai.providers.openai.model' => 'paid-model',
            'ai.providers.ollama.enabled' => false, 'ai.providers.gemini.enabled' => false,
        ]);
    }

    public function test_rate_limited_primary_falls_back_and_records_both_attempts(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'busy']], 429),
            'openrouter.ai/*' => Http::response(['id' => 'fallback-1', 'model' => 'free-selected', 'choices' => [['message' => ['content' => 'Grounded explanation.']]], 'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 5]], 200),
        ]);
        $user = $this->userWithPreferences();
        $result = app(AiManager::class)->execute($this->request($user), $user);

        $this->assertSame('openrouter', $result->provider);
        $this->assertDatabaseHas('ai_usage_events', ['provider' => 'groq', 'status' => 'failed', 'error_code' => 'rate_limited', 'fallback_used' => false]);
        $this->assertDatabaseHas('ai_usage_events', ['provider' => 'openrouter', 'status' => 'success', 'fallback_used' => true]);
        Http::assertSentCount(2);
    }

    public function test_paid_provider_is_never_called_when_paid_fallback_is_prohibited(): void
    {
        config(['ai.providers.groq.enabled' => false, 'ai.providers.openrouter.enabled' => false]);
        Http::fake();
        $user = $this->userWithPreferences();

        $this->expectException(TutorUnavailableException::class);
        try {
            app(AiManager::class)->execute($this->request($user), $user);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_non_retryable_rejection_does_not_fan_out_to_other_providers(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'invalid']], 400),
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'must not run']]]], 200),
        ]);
        $user = $this->userWithPreferences();

        try {
            app(AiManager::class)->execute($this->request($user), $user);
            $this->fail('Expected the request to fail closed.');
        } catch (TutorUnavailableException) {
            Http::assertSentCount(1);
            Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.groq.com'));
        }
    }

    public function test_cloud_disabled_preference_excludes_every_cloud_provider(): void
    {
        Http::fake();
        $user = $this->userWithPreferences(cloud: false, local: true);

        $this->expectException(TutorUnavailableException::class);
        try {
            app(AiManager::class)->execute($this->request($user), $user);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_local_only_preference_can_use_ollama_without_cloud_calls(): void
    {
        config(['ai.routing' => ['ollama', 'groq'], 'ai.providers.ollama.enabled' => true, 'ai.providers.ollama.model' => 'local-model']);
        Http::fake(['127.0.0.1:11434/*' => Http::response(['model' => 'local-model', 'message' => ['content' => 'Local explanation'], 'prompt_eval_count' => 5, 'eval_count' => 3], 200)]);
        $user = $this->userWithPreferences(cloud: false, local: true);
        $result = app(AiManager::class)->execute($this->request($user), $user);

        $this->assertSame('ollama', $result->provider);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '127.0.0.1:11434'));
    }

    private function userWithPreferences(bool $cloud = true, bool $local = true): User
    {
        $user = User::factory()->create(['is_active' => true]);
        LearnerProfile::query()->create(['user_id' => $user->id, 'ai_tutor_enabled' => true, 'allow_cloud_ai' => $cloud, 'allow_local_ai' => $local]);

        return $user->load('learnerProfile');
    }

    private function request(User $user): AiRequest
    {
        return new AiRequest('tutoring', 'text_generation', 'Use course evidence.', 'Explain pips.', 'forex_tutor_v1', null, false, $user->id, 'test-'.uniqid());
    }
}
