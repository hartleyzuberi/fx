<?php

namespace App\Services\Assessment;

use App\AI\Data\AiRequest;
use App\AI\Prompts\PromptRegistry;
use App\AI\Services\AiManager;
use App\Exceptions\TutorUnavailableException;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class SemanticAssessmentService
{
    public function __construct(private AiManager $ai, private PromptRegistry $prompts) {}

    /**
     * @param  array<string, mixed>  $rubric
     * @param  list<array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    public function grade(User $user, string $question, string $answer, array $rubric, array $sources, ?string $requestId = null): array
    {
        $prompt = $this->prompts->semanticGrader();
        $schema = [
            'type' => 'object', 'additionalProperties' => false,
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['correct', 'partially_correct', 'incorrect', 'misconception', 'unable_to_assess']],
                'mastery_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'correct_concepts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'missing_concepts' => ['type' => 'array', 'items' => ['type' => 'string']],
                'misconceptions' => ['type' => 'array', 'items' => ['type' => 'string']],
                'feedback' => ['type' => 'string'], 'follow_up_question' => ['type' => ['string', 'null']],
                'requires_remediation' => ['type' => 'boolean'], 'citations' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['status', 'mastery_score', 'confidence', 'correct_concepts', 'missing_concepts', 'misconceptions', 'feedback', 'follow_up_question', 'requires_remediation', 'citations'],
        ];
        $context = collect($sources)->map(fn (array $source, int $index): string => '[S'.($index + 1)."] {$source['document']}, physical page {$source['page']}\n{$source['text']}")->implode("\n\n");
        $response = $this->ai->execute(new AiRequest(
            'semantic_assessment', 'structured_output', $prompt['prompt'],
            "QUESTION\n{$question}\n\nLEARNER ANSWER\n{$answer}\n\nRUBRIC\n".json_encode($rubric, JSON_THROW_ON_ERROR)."\n\nCOURSE EXCERPTS\n{$context}",
            $prompt['version'], $schema, false, $user->id, $requestId,
        ), $user);
        $decoded = json_decode($response->text, true);
        $validation = Validator::make(is_array($decoded) ? $decoded : [], [
            'status' => ['required', 'in:correct,partially_correct,incorrect,misconception,unable_to_assess'],
            'mastery_score' => ['required', 'integer', 'between:0,100'], 'confidence' => ['required', 'numeric', 'between:0,1'],
            'correct_concepts' => ['present', 'array'], 'missing_concepts' => ['present', 'array'], 'misconceptions' => ['present', 'array'],
            'feedback' => ['required', 'string', 'max:5000'], 'follow_up_question' => ['present', 'nullable', 'string', 'max:1000'],
            'requires_remediation' => ['present', 'boolean'], 'citations' => ['present', 'array'],
        ]);
        if ($validation->fails() || ($decoded['status'] ?? null) === 'unable_to_assess') {
            throw new TutorUnavailableException('Semantic assessment could not be validated.');
        }
        $validated = $validation->validated();
        if ((float) $validated['confidence'] < 0.7) {
            throw new TutorUnavailableException('Semantic assessment confidence is insufficient for mastery evidence.');
        }

        return [...$validated, '_provider' => $response->provider, '_model' => $response->model, '_response_id' => $response->responseId, '_prompt_version' => $prompt['version']];
    }
}
