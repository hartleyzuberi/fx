<?php

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuestionBankValidator
{
    private const CHOICE_TYPES = ['single_choice', 'scenario_choice', 'misconception_choice', 'multiple_select'];

    private const SUPPORTED_TYPES = [
        'single_choice', 'multiple_select', 'true_false', 'matching', 'ordering', 'numeric', 'calculation',
        'scenario_choice', 'misconception_choice', 'classification', 'fill_blank', 'free_response', 'manual_review',
    ];

    /** @return array{errors: list<string>, warnings: list<string>, metrics: array<string, int>} */
    public function validate(): array
    {
        $errors = [];
        $warnings = [];
        $questions = Question::query()->get();
        $chapterAssessments = Assessment::query()->where('assessment_type', 'chapter_quiz')->pluck('id');
        $formalAssessments = Assessment::query()->whereIn('assessment_type', ['gate_exam', 'final_exam'])->get();

        foreach ($questions as $question) {
            if (! in_array($question->question_type, self::SUPPORTED_TYPES, true)) {
                $errors[] = "Question {$question->id} uses unsupported type {$question->question_type}.";
            }

            if ($chapterAssessments->contains($question->assessment_id) && $question->question_type === 'free_response') {
                if (! $question->hasApprovedStructuredVariant()) {
                    $errors[] = "Chapter question {$question->id} has no approved deterministic structured alternative.";
                } else {
                    $this->validateStructuredPayload(
                        $question->id.' structured variant',
                        (string) $question->structured_type,
                        $question->structured_choices,
                        $question->structured_answer_key,
                        $errors,
                    );
                }
            }

            if ($question->question_type !== 'free_response' && $question->status === 'approved') {
                $this->validateStructuredPayload(
                    'Question '.$question->id,
                    $question->question_type,
                    $question->choices,
                    $question->answer_key,
                    $errors,
                );
            }
        }

        foreach ($formalAssessments as $assessment) {
            $structured = $questions
                ->where('assessment_id', $assessment->id)
                ->filter(fn (Question $question): bool => $question->status === 'approved'
                    && $question->question_type !== 'free_response'
                    && ($question->metadata['completion_role'] ?? null) === 'structured_gate');
            if ($structured->isEmpty()) {
                $errors[] = "Formal assessment {$assessment->title} has no approved deterministic self-study question set.";
            }
            $activeManual = $questions
                ->where('assessment_id', $assessment->id)
                ->filter(function (Question $question): bool {
                    $key = $question->answer_key ?? [];

                    return $question->status === 'approved' && ($key['kind'] ?? null) === 'manual_review';
                });
            if ($activeManual->isNotEmpty()) {
                $errors[] = "Formal assessment {$assessment->title} still contains mandatory manual-review questions.";
            }

            foreach ($structured as $question) {
                $metadata = $question->metadata ?? [];
                $hasProvenance = isset($metadata['source_question_id'])
                    || isset($metadata['canonical_requirement'])
                    || $question->source_segment_id !== null;
                if (! $hasProvenance) {
                    $errors[] = "Formal question {$question->id} in {$assessment->title} has no canonical provenance pointer.";
                }
            }
        }

        $gateDDrills = $questions->filter(fn (Question $question): bool => ($question->metadata['evidence_key'] ?? null) === 'gate_d_position_sizing_drill');
        if ($formalAssessments->isNotEmpty() && $gateDDrills->count() < 50) {
            $errors[] = 'Gate D requires at least 50 deterministic position-sizing drills; found '.$gateDDrills->count().'.';
        }

        $requiredEvidence = [
            'gate_a_platform_and_regulator_competence' => 1,
            'gate_b_annotated_charts' => 100,
            'gate_c_weekly_macro_sheets' => 4,
            'gate_c_event_studies' => 20,
            'gate_e_strategy_decision' => 1,
            'gate_f_review_process' => 1,
            'gate_g_broker_verification' => 1,
            'gate_g_micro_live_sizing_plan' => 1,
            'gate_g_descaling_triggers' => 1,
            'gate_g_operations_continuity' => 1,
            'graduation_professional_plan' => 1,
        ];
        $assignments = DB::table('practice_assignments')->where('assignment_type', 'gate_evidence')->get();
        foreach ($requiredEvidence as $key => $minimum) {
            $assignment = $assignments->first(function (object $row) use ($key): bool {
                $requirements = json_decode((string) $row->requirements, true);

                return is_array($requirements) && ($requirements['evidence_key'] ?? null) === $key;
            });
            if (! $assignment) {
                if ($formalAssessments->isNotEmpty()) {
                    $errors[] = "Canonical evidence assignment {$key} is missing.";
                }
                continue;
            }
            if ((int) ($assignment->required_observations ?? 0) < $minimum) {
                $errors[] = "Evidence assignment {$key} requires {$assignment->required_observations} observations; canonical minimum is {$minimum}.";
            }
        }

        $duplicatePrompts = Question::query()
            ->select('assessment_id', 'prompt', DB::raw('COUNT(*) as aggregate'))
            ->where('status', 'approved')
            ->groupBy('assessment_id', 'prompt')
            ->having('aggregate', '>', 1)
            ->get();
        foreach ($duplicatePrompts as $duplicate) {
            $warnings[] = "Assessment {$duplicate->assessment_id} contains {$duplicate->aggregate} approved questions with the same prompt.";
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'metrics' => [
                'questions_total' => $questions->count(),
                'chapter_questions' => $questions->whereIn('assessment_id', $chapterAssessments)->count(),
                'chapter_structured_variants_approved' => $questions->whereIn('assessment_id', $chapterAssessments)->filter(fn (Question $question): bool => $question->hasApprovedStructuredVariant())->count(),
                'formal_assessments' => $formalAssessments->count(),
                'formal_structured_questions' => $questions->filter(fn (Question $question): bool => ($question->metadata['completion_role'] ?? null) === 'structured_gate' && $question->status === 'approved')->count(),
                'gate_d_position_sizing_drills' => $gateDDrills->count(),
                'gate_evidence_assignments' => $assignments->count(),
                'errors' => count(array_unique($errors)),
                'warnings' => count(array_unique($warnings)),
            ],
        ];
    }

    /** @param array<string, mixed>|list<mixed>|null $choices @param array<string, mixed>|null $answerKey @param list<string> $errors */
    private function validateStructuredPayload(string $label, string $type, ?array $choices, ?array $answerKey, array &$errors): void
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true) || $type === 'free_response' || $type === 'manual_review') {
            $errors[] = "{$label} has invalid structured type {$type}.";
            return;
        }
        if (! is_array($answerKey) || $answerKey === []) {
            $errors[] = "{$label} is missing a protected answer key.";
            return;
        }

        if (in_array($type, self::CHOICE_TYPES, true)) {
            if (! is_array($choices) || $choices === []) {
                $errors[] = "{$label} has no options.";
                return;
            }
            $options = collect($choices)->filter(fn (mixed $option): bool => is_array($option) && isset($option['id'], $option['text']));
            if ($options->count() !== count($choices)) {
                $errors[] = "{$label} contains a malformed option.";
                return;
            }
            $ids = $options->pluck('id')->map(fn (mixed $id): string => (string) $id);
            $texts = $options->pluck('text')->map(fn (mixed $text): string => mb_strtolower(trim((string) $text)));
            if ($ids->unique()->count() !== $ids->count()) {
                $errors[] = "{$label} contains duplicate option IDs.";
            }
            if ($texts->unique()->count() !== $texts->count()) {
                $errors[] = "{$label} contains duplicate option text.";
            }

            $correct = $type === 'multiple_select'
                ? $this->listOfStrings($answerKey['correct'] ?? [])
                : $this->listOfStrings([$answerKey['correct'] ?? null]);
            if ($correct === []) {
                $errors[] = "{$label} has no correct option configured.";
            }
            foreach ($correct as $id) {
                if (! $ids->contains($id)) {
                    $errors[] = "{$label} references missing correct option {$id}.";
                }
            }
            if ($type !== 'multiple_select' && count($correct) !== 1) {
                $errors[] = "{$label} must have exactly one correct option.";
            }
        }

        if (in_array($type, ['numeric', 'calculation'], true) && ! isset($answerKey['value']) && ! isset($answerKey['expected'])) {
            $errors[] = "{$label} is missing an expected numeric value.";
        }
        if ($type === 'fill_blank' && $this->listOfStrings($answerKey['accepted'] ?? []) === []) {
            $errors[] = "{$label} has no accepted fill-blank answers.";
        }
        if (in_array($type, ['matching', 'classification'], true) && ! is_array($answerKey['pairs'] ?? $answerKey['correct'] ?? null)) {
            $errors[] = "{$label} is missing matching/classification pairs.";
        }
        if ($type === 'ordering' && $this->listOfStrings($answerKey['order'] ?? $answerKey['correct'] ?? []) === []) {
            $errors[] = "{$label} is missing the expected order.";
        }
    }

    /** @return list<string> */
    private function listOfStrings(mixed $value): array
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        return array_values(array_map(
            fn (mixed $item): string => trim((string) $item),
            array_filter($value, fn (mixed $item): bool => $item !== null && trim((string) $item) !== ''),
        ));
    }
}
