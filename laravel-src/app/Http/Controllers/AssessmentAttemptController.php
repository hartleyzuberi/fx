<?php

namespace App\Http\Controllers;

use App\AI\Services\AiFeature;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Question;
use App\Services\Assessment\QuestionDeliveryService;
use App\Services\Assessment\RubricGrader;
use App\Services\Assessment\SemanticAssessmentService;
use App\Services\Assessment\StructuredQuestionGrader;
use App\Services\Learning\MasteryService;
use App\Services\Learning\ProgressionService;
use App\Services\Tutor\CourseRetriever;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssessmentAttemptController extends Controller
{
    public function store(
        Request $request,
        LearningUnit $unit,
        RubricGrader $rubricGrader,
        StructuredQuestionGrader $structuredGrader,
        QuestionDeliveryService $delivery,
        SemanticAssessmentService $aiGrader,
        CourseRetriever $retriever,
        ProgressionService $progression,
        MasteryService $mastery,
    ): RedirectResponse {
        $user = $request->user();
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        $assessment = Assessment::query()->with('questions')->where('learning_unit_id', $unit->id)->where('assessment_type', 'chapter_quiz')->firstOrFail();
        $validated = $request->validate(['answers' => ['required', 'array']]);

        $normalizedAnswers = [];
        foreach ($assessment->questions as $question) {
            if (! array_key_exists($question->id, $validated['answers'])) {
                throw ValidationException::withMessages(["answers.{$question->id}" => 'This question requires an answer.']);
            }
            $effectiveType = $delivery->effectiveType($user, $question);
            $normalizedAnswers[$question->id] = $this->normalizeAnswer($effectiveType, $validated['answers'][$question->id], $question);
        }

        DB::transaction(function () use ($assessment, $enrollment, $unit, $normalizedAnswers, $rubricGrader, $structuredGrader, $delivery, $aiGrader, $retriever, $progression, $mastery, $user): void {
            $attemptId = (string) Str::ulid();
            $attemptNumber = ((int) DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->max('attempt_number')) + 1;
            DB::table('assessment_attempts')->insert([
                'id' => $attemptId,
                'enrollment_id' => $enrollment->id,
                'assessment_id' => $assessment->id,
                'attempt_number' => $attemptNumber,
                'status' => 'grading',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $earned = 0.0;
            $maximum = 0.0;
            foreach ($assessment->questions as $question) {
                $answer = $normalizedAnswers[$question->id];
                $effectiveType = $delivery->effectiveType($user, $question);
                $effectiveKey = $delivery->effectiveAnswerKey($user, $question);
                $structured = $effectiveType !== 'free_response';

                if ($structured) {
                    $result = $structuredGrader->grade($question, $answer, $effectiveType, $effectiveKey);
                    $graderType = 'structured_'.$effectiveType;
                    $gradingVersion = 'structured-v1';
                } else {
                    $answerText = (string) $answer;
                    $result = $rubricGrader->grade($answerText, $effectiveKey);
                    $graderType = 'deterministic_rubric';
                    $gradingVersion = 'rubric-v1';

                    if (
                        app(AiFeature::class)->enabled('AI_SEMANTIC_GRADING_ENABLED', (bool) config('ai.semantic_grading_enabled'))
                        && $user->learnerProfile?->ai_tutor_enabled
                        && in_array($result['status'], ['partially_correct', 'incorrect'], true)
                    ) {
                        try {
                            $result = $aiGrader->grade(
                                $user,
                                $question->prompt,
                                $answerText,
                                $effectiveKey,
                                $retriever->retrieve($unit->id, $question->prompt, 4),
                                $attemptId.':'.$question->id,
                            );
                            $graderType = 'ai_semantic';
                            $gradingVersion = 'semantic-v1';
                        } catch (\Throwable) {
                            // The deterministic rubric remains authoritative if semantic grading is unavailable.
                        }
                    }
                }

                $awarded = ((float) $question->points) * ($result['mastery_score'] / 100);
                $earned += $awarded;
                $maximum += (float) $question->points;
                $answerId = (string) Str::ulid();
                $answerText = is_array($answer) ? null : (string) $answer;
                $answerData = is_array($answer) ? json_encode($answer, JSON_THROW_ON_ERROR) : null;
                $questionSnapshot = [
                    'id' => $question->id,
                    'version' => $question->question_version ?? '1',
                    'prompt' => $question->prompt,
                    'type' => $effectiveType,
                    'choices' => $delivery->publicChoices($delivery->effectiveChoices($user, $question)),
                    'points' => (float) $question->points,
                ];

                DB::table('answer_attempts')->insert([
                    'id' => $answerId,
                    'assessment_attempt_id' => $attemptId,
                    'question_id' => $question->id,
                    'question_version' => $question->question_version ?? '1',
                    'revision' => 1,
                    'answer_text' => $answerText,
                    'answer_data' => $answerData,
                    'grading_status' => $result['status'],
                    'grading_version' => $gradingVersion,
                    'awarded_points' => $awarded,
                    'deterministic_result' => json_encode($result, JSON_THROW_ON_ERROR),
                    'question_snapshot' => json_encode($questionSnapshot, JSON_THROW_ON_ERROR),
                    'answered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('grading_recommendations')->insert([
                    'id' => (string) Str::ulid(),
                    'answer_attempt_id' => $answerId,
                    'grader_type' => $graderType,
                    'provider' => $result['_provider'] ?? null,
                    'model' => $result['_model'] ?? null,
                    'prompt_version' => $result['_prompt_version'] ?? null,
                    'rubric_version' => $gradingVersion,
                    'course_version' => $unit->curriculum_version_id,
                    'status' => $result['status'],
                    'mastery_score' => $result['mastery_score'],
                    'correct_concepts' => json_encode($result['correct_concepts'], JSON_THROW_ON_ERROR),
                    'missing_concepts' => json_encode($result['missing_concepts'], JSON_THROW_ON_ERROR),
                    'misconceptions' => json_encode($result['misconceptions'], JSON_THROW_ON_ERROR),
                    'feedback' => $result['feedback'],
                    'follow_up_question' => $result['follow_up_question'] ?? null,
                    'requires_remediation' => $result['requires_remediation'],
                    'citations' => json_encode($result['citations'] ?? [], JSON_THROW_ON_ERROR),
                    'raw_metadata' => isset($result['_response_id']) ? json_encode(['response_id' => $result['_response_id'], 'confidence' => $result['confidence'] ?? null], JSON_THROW_ON_ERROR) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $mastery->recordAnswer($enrollment, $question, $answerId, $result);
            }

            $score = $maximum > 0 ? round(($earned / $maximum) * 100, 2) : 0;
            $passed = $score >= $assessment->passing_score;
            $decision = $progression->evaluateAfterAssessment($enrollment, $unit, $score, $passed);
            DB::table('assessment_attempts')->where('id', $attemptId)->update([
                'status' => 'graded',
                'score' => $score,
                'maximum_score' => 100,
                'passed' => $passed,
                'submitted_at' => now(),
                'grading_summary' => json_encode($decision, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });

        return to_route('assessments.show', $unit)->with('success', 'Your attempt has been graded and saved.');
    }

    private function normalizeAnswer(string $type, mixed $answer, Question $question): mixed
    {
        $field = "answers.{$question->id}";

        if ($type === 'free_response' || $type === 'fill_blank') {
            if (! is_string($answer) || mb_strlen(trim($answer)) < ($type === 'free_response' ? 2 : 1)) {
                throw ValidationException::withMessages([$field => 'Enter an answer before submitting.']);
            }

            return mb_substr(trim($answer), 0, $type === 'free_response' ? 5000 : 500);
        }

        if (in_array($type, ['single_choice', 'scenario_choice', 'misconception_choice', 'true_false'], true)) {
            if (! is_scalar($answer) || trim((string) $answer) === '') {
                throw ValidationException::withMessages([$field => 'Select an answer before submitting.']);
            }

            return trim((string) $answer);
        }

        if (in_array($type, ['numeric', 'calculation'], true)) {
            if (! is_scalar($answer) || ! is_numeric($answer)) {
                throw ValidationException::withMessages([$field => 'Enter a valid number.']);
            }

            return (string) $answer;
        }

        if (in_array($type, ['multiple_select', 'matching', 'ordering', 'classification'], true)) {
            if (! is_array($answer) || $answer === []) {
                throw ValidationException::withMessages([$field => 'Complete this question before submitting.']);
            }

            return $answer;
        }

        throw ValidationException::withMessages([$field => "Unsupported question type [{$type}]."]);
    }
}
