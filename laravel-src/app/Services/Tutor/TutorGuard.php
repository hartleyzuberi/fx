<?php

namespace App\Services\Tutor;

use App\AI\Services\AiFeature;
use Illuminate\Container\Container;

class TutorGuard
{
    /**
     * @param  list<string>  $protectedPrompts
     * @return array{allowed: bool, mode: string, sanitized_prompt: string, response: string|null, safety_status: string}
     */
    public function inspect(string $prompt, bool $activeAssessment, array $protectedPrompts = []): array
    {
        $normalized = mb_strtolower($prompt);
        $injection = preg_match('/ignore (?:all |the )?(?:previous|system)|reveal (?:the )?(?:system|developer) prompt|bypass (?:the )?(?:rules|guard)|pretend i scored|mark (?:this|me) correct|unlock (?:the )?(?:next|week|phase)|update my mastery|<script|jailbreak/i', $prompt);
        if ($injection) {
            return ['allowed' => false, 'mode' => 'blocked', 'sanitized_prompt' => '', 'response' => 'I can help with the course concept, but I cannot follow instructions that try to replace the tutor or assessment rules. Ask a direct forex learning question instead.', 'safety_status' => 'prompt_injection_blocked'];
        }

        $resemblesProtectedPrompt = $activeAssessment && collect($protectedPrompts)->contains(fn (string $question): bool => $this->overlaps($normalized, mb_strtolower($question)));
        $answerRequest = $activeAssessment && (preg_match('/(?:give|tell|show|reveal|what is)\s+(?:me\s+)?(?:the\s+)?(?:answer|solution)|answer\s+(?:to\s+)?(?:question|q)\s*\d+/i', $normalized) || $resemblesProtectedPrompt);
        if ($answerRequest) {
            return ['allowed' => false, 'mode' => 'socratic_hint', 'sanitized_prompt' => '', 'response' => 'I cannot provide an active assessment answer. I can help you reconstruct the underlying idea: identify the base currency, the quote currency, and what one unit of the base buys.', 'safety_status' => 'assessment_answer_shielded'];
        }

        if (preg_match('/\b(?:should i|tell me to|signal|entry now|buy|sell)\b.{0,40}\b(?:eur|usd|gbp|jpy|currency|pair|forex)\b|\b(?:buy|sell)\s+(?:eur|usd|gbp|jpy)[\w\/.-]*/i', $normalized)) {
            return ['allowed' => false, 'mode' => 'educational_boundary', 'sanitized_prompt' => '', 'response' => "I can help you analyze how a documented strategy evaluates conditions, but I won't generate an ad-hoc live trading signal.", 'safety_status' => 'live_signal_blocked'];
        }

        $container = Container::getInstance();
        $externalRetrieval = $container->bound('config')
            ? $container->make(AiFeature::class)->enabled('AI_EXTERNAL_RETRIEVAL_ENABLED', (bool) config('ai.external_retrieval_enabled'))
            : false;
        if (! $externalRetrieval && preg_match('/\b(?:current|currently|today|latest|right now|present)\b.{0,80}\b(?:rate|cpi|inflation|broker|market|price|conditions|licensed)\b/i', $normalized)) {
            return ['allowed' => false, 'mode' => 'current_information', 'sanitized_prompt' => '', 'response' => 'That question requires current authoritative information, and live external retrieval is not configured. I will not guess from static course material.', 'safety_status' => 'current_information_unavailable'];
        }

        $sanitized = trim((string) preg_replace('/https?:\/\/\S+/i', '[external URL omitted]', $prompt));

        return ['allowed' => true, 'mode' => 'course_tutor', 'sanitized_prompt' => $sanitized, 'response' => null, 'safety_status' => str_contains($sanitized, '[external URL omitted]') ? 'external_url_not_fetched' : 'allowed'];
    }

    private function overlaps(string $prompt, string $question): bool
    {
        $tokens = fn (string $value): array => array_values(array_unique(array_filter(
            preg_split('/[^\pL\pN]+/u', $value) ?: [],
            fn (string $word): bool => mb_strlen($word) >= 4 && ! in_array($word, ['what', 'when', 'where', 'which', 'does', 'should', 'could', 'would', 'your', 'about'], true),
        )));
        $promptTokens = $tokens($prompt);
        $questionTokens = $tokens($question);

        return count($promptTokens) >= 2 && count(array_intersect($promptTokens, $questionTokens)) >= min(2, count($promptTokens));
    }
}
