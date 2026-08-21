<?php

namespace App\AI\Services;

use App\AI\Data\AiRequest;
use App\AI\Data\AiResponse;
use App\AI\Exceptions\ProviderException;
use App\Exceptions\TutorUnavailableException;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

class AiManager
{
    public function __construct(
        private AiRouter $router,
        private ProviderRegistry $registry,
        private CircuitBreaker $circuit,
        private AiUsageService $usage,
    ) {}

    public function execute(AiRequest $request, ?User $user = null): AiResponse
    {
        $this->guardRateLimit($request);
        $candidates = $this->router->candidates($request, $user);
        if ($candidates === []) {
            throw new TutorUnavailableException('AI Tutor is temporarily unavailable. Your lessons, course search, exercises and progress remain available.');
        }

        $last = null;
        foreach ($candidates as $index => $candidate) {
            $started = microtime(true);
            try {
                $response = $this->registry->provider($candidate['key'])->generate($request, (string) $candidate['model']);
                $latency = (int) ((microtime(true) - $started) * 1000);
                $this->circuit->succeed($candidate['key'], $request->capability);
                $this->usage->success($request, $response, $latency, $index > 0);

                return $response;
            } catch (ProviderException $exception) {
                $last = $exception;
                $latency = (int) ((microtime(true) - $started) * 1000);
                $this->usage->failure($request, $candidate['key'], (string) $candidate['model'], $latency, $index > 0, $exception->errorCode, $exception->getMessage());
                if ($exception->retryable) {
                    $this->circuit->fail($candidate['key'], $request->capability);

                    continue;
                }
                break;
            }
        }

        throw new TutorUnavailableException($last?->errorCode === 'rate_limited'
            ? 'The tutor has reached its temporary limit. Continue studying normally and try again later.'
            : 'AI Tutor is temporarily unavailable. Your lessons, course search, exercises and progress remain available.');
    }

    private function guardRateLimit(AiRequest $request): void
    {
        if (! $request->userId || $request->task === 'health_check') {
            return;
        }
        $daily = "ai:user:{$request->userId}:".now()->toDateString();
        if (RateLimiter::tooManyAttempts($daily, max(1, (int) config('ai.max_requests_per_user_per_day')))) {
            throw new TutorUnavailableException('Your daily tutor allowance has been reached. Course search and all core learning remain available.');
        }
        RateLimiter::hit($daily, 86400);
    }
}
