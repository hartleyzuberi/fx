<?php

namespace App\Services\Assessment;

use App\AI\Services\AiFeature;
use App\Models\Question;
use App\Models\User;

class QuestionDeliveryService
{
    public function __construct(private readonly AiFeature $features) {}

    public function effectiveType(User $user, Question $question): string
    {
        if ($question->question_type !== 'free_response') {
            return $question->question_type;
        }

        if ($this->semanticPathAvailable($user)) {
            return 'free_response';
        }

        if ($question->hasApprovedStructuredVariant()) {
            return (string) $question->structured_type;
        }

        return 'free_response';
    }

    /** @return array<string, mixed> */
    public function effectiveAnswerKey(User $user, Question $question): array
    {
        return $this->usesStructuredVariant($user, $question)
            ? ($question->structured_answer_key ?? [])
            : ($question->answer_key ?? []);
    }

    /** @return array<array-key, mixed>|null */
    public function effectiveChoices(User $user, Question $question): ?array
    {
        return $this->usesStructuredVariant($user, $question)
            ? $question->structured_choices
            : $question->choices;
    }

    public function usesStructuredVariant(User $user, Question $question): bool
    {
        return $question->question_type === 'free_response'
            && ! $this->semanticPathAvailable($user)
            && $question->hasApprovedStructuredVariant();
    }

    public function semanticPathAvailable(User $user): bool
    {
        return $this->features->enabled('AI_SEMANTIC_GRADING_ENABLED', (bool) config('ai.semantic_grading_enabled'))
            && (bool) $user->learnerProfile?->ai_tutor_enabled;
    }

    /**
     * @param array<array-key, mixed>|null $choices
     * @return array<array-key, mixed>|null
     */
    public function publicChoices(?array $choices): ?array
    {
        if ($choices === null) {
            return null;
        }

        return $this->stripProtectedMetadata($choices);
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function stripProtectedMetadata(array $value): array
    {
        $protected = [
            'correct',
            'correct_option_ids',
            'answer_key',
            'structured_answer_key',
            'misconception',
            'misconceptions',
            'is_correct',
            'score',
            'points',
        ];

        $clean = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $protected, true)) {
                continue;
            }
            $clean[$key] = is_array($item) ? $this->stripProtectedMetadata($item) : $item;
        }

        return $clean;
    }
}
