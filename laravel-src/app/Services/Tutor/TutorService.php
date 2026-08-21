<?php

namespace App\Services\Tutor;

use App\AI\Data\AiRequest;
use App\AI\Prompts\PromptRegistry;
use App\AI\Services\AiManager;
use App\AI\Services\RuntimeModeResolver;
use App\Exceptions\TutorUnavailableException;
use App\Models\LearningUnit;
use App\Models\User;

class TutorService
{
    public function __construct(
        private TutorGuard $guard,
        private CourseRetriever $retriever,
        private AiManager $ai,
        private PromptRegistry $prompts,
        private RuntimeModeResolver $modes,
    ) {}

    /**
     * @param  list<string>  $protectedPrompts
     * @return array<string, mixed>
     */
    public function reply(User $user, LearningUnit $unit, string $prompt, bool $activeAssessment = false, array $protectedPrompts = []): array
    {
        $decision = $this->guard->inspect($prompt, $activeAssessment, $protectedPrompts);
        if (! $decision['allowed']) {
            return ['text' => $decision['response'], 'citations' => [], 'provider' => null, 'model' => null, 'response_id' => null, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0], 'safety_status' => $decision['safety_status'], 'runtime_mode' => $this->modes->resolve($user), 'generated' => false];
        }
        $sources = $this->retriever->retrieve($unit->id, $decision['sanitized_prompt']);
        if ($sources === []) {
            return ['text' => 'I could not find a mapped course passage for that question. Try asking about the current concept or use Search Course.', 'citations' => [], 'provider' => null, 'model' => null, 'response_id' => null, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0], 'safety_status' => 'no_grounding_found', 'runtime_mode' => $this->modes->resolve($user), 'generated' => false];
        }
        $citations = collect($sources)->map(fn (array $source, int $index): array => ['label' => 'S'.($index + 1), 'source_segment_id' => $source['id'], 'document' => $source['document'], 'page' => $source['page']])->all();
        $prompt = $this->prompts->tutor();
        $context = collect($sources)->map(fn (array $source, int $index): string => '[S'.($index + 1)."] {$source['document']}, physical page {$source['page']}\n{$source['text']}")->implode("\n\n");
        try {
            $reply = $this->ai->execute(new AiRequest(
                'tutoring', 'text_generation', $prompt['prompt'],
                "CURRENT UNIT\n{$unit->title}\n\nCOURSE EXCERPTS\n{$context}\n\nLEARNER QUESTION\n{$decision['sanitized_prompt']}",
                $prompt['version'], null, false, $user->id, request()->input('request_id'), ['learning_unit_id' => $unit->id],
            ), $user);

            return ['text' => $reply->text, 'citations' => $citations, 'provider' => $reply->provider, 'model' => $reply->model,
                'response_id' => $reply->responseId, 'usage' => $reply->usage, 'safety_status' => $decision['safety_status'],
                'runtime_mode' => $this->modes->resolve($user), 'generated' => true];
        } catch (TutorUnavailableException) {
            return ['text' => $this->retrievalReply($sources), 'citations' => $citations, 'provider' => null, 'model' => null,
                'response_id' => null, 'usage' => ['input_tokens' => 0, 'output_tokens' => 0], 'safety_status' => 'retrieval_only',
                'runtime_mode' => 'RETRIEVAL_ONLY', 'generated' => false];
        }
    }

    /** @param list<array{id: string, text: string, document: string, page: int, heading: array<mixed>}> $sources */
    private function retrievalReply(array $sources): string
    {
        $snippets = collect($sources)->take(3)->map(function (array $source, int $index): string {
            $text = trim((string) preg_replace('/\s+/u', ' ', $source['text']));

            return '[S'.($index + 1).'] '.mb_substr($text, 0, 360).(mb_strlen($text) > 360 ? '…' : '');
        })->implode("\n\n");

        return "Tutor explanation is temporarily unavailable, but Search Course found these authoritative passages:\n\n{$snippets}";
    }
}
