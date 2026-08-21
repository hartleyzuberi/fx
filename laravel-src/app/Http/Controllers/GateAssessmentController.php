<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Question;
use App\Models\UnitProgress;
use App\Services\Assessment\QuestionDeliveryService;
use App\Services\Assessment\StructuredQuestionGrader;
use App\Services\Learning\MasteryService;
use App\Services\Learning\ProgressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GateAssessmentController extends Controller
{
    public function show(LearningUnit $unit, QuestionDeliveryService $delivery): Response
    {
        [$enrollment, $assessment] = $this->context($unit);
        $latest = DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->latest('attempt_number')->first();
        $structured = $this->structuredQuestions($assessment);
        $questions = $structured->isNotEmpty() ? $structured : $assessment->questions;
        $mode = $structured->isNotEmpty() ? 'deterministic' : 'manual_review';

        return Inertia::render('assessment/gate', [
            'unit' => ['slug' => $unit->slug, 'title' => $unit->title],
            'assessment' => [
                'title' => $assessment->title,
                'passingScore' => $assessment->passing_score,
                'mode' => $mode,
                'questions' => $questions->map(fn (Question $question): array => [
                    'id' => $question->id,
                    'position' => $question->position,
                    'prompt' => $question->prompt,
                    'type' => $question->question_type,
                    'choices' => $delivery->publicChoices($question->choices),
                ])->values(),
            ],
            'latestAttempt' => $latest ? ['status' => $latest->status, 'score' => $latest->score, 'passed' => $latest->passed, 'summary' => json_decode($latest->grading_summary ?? '{}', true)] : null,
        ]);
    }

    public function store(
        Request $request,
        LearningUnit $unit,
        StructuredQuestionGrader $grader,
        QuestionDeliveryService $delivery,
        ProgressionService $progression,
        MasteryService $mastery,
    ): RedirectResponse {
        [$enrollment, $assessment] = $this->context($unit);
        abort_unless(DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->whereIn('assessment_id', Assessment::query()->where('learning_unit_id', $unit->id)->where('assessment_type', 'chapter_quiz')->pluck('id'))->where('passed', true)->exists(), 403, 'Pass the chapter mastery check before submitting this gate.');
        $structured = $this->structuredQuestions($assessment);

        if ($structured->isEmpty()) {
            return $this->storeManualReview($request, $unit, $enrollment, $assessment);
        }

        $validated = $request->validate(['answers' => ['required', 'array']]);
        $answers = [];
        foreach ($structured as $question) {
            if (! array_key_exists($question->id, $validated['answers'])) {
                throw ValidationException::withMessages(["answers.{$question->id}" => 'This gate question requires an answer.']);
            }
            $answers[$question->id] = $this->normalizeStructuredAnswer($question, $validated['answers'][$question->id]);
        }

        DB::transaction(function () use ($assessment, $enrollment, $unit, $structured, $answers, $grader, $delivery, $progression, $mastery): void {
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
            foreach ($structured as $question) {
                $answer = $answers[$question->id];
                $result = $grader->grade($question, $answer, $question->question_type, $question->answer_key ?? []);
                $awarded = ((float) $question->points) * ($result['mastery_score'] / 100);
                $earned += $awarded;
                $maximum += (float) $question->points;
                $answerId = (string) Str::ulid();
                $answerText = is_array($answer) ? null : (string) $answer;
                $answerData = is_array($answer) ? json_encode($answer, JSON_THROW_ON_ERROR) : null;

                DB::table('answer_attempts')->insert([
                    'id' => $answerId,
                    'assessment_attempt_id' => $attemptId,
                    'question_id' => $question->id,
                    'question_version' => $question->question_version ?? '1',
                    'revision' => 1,
                    'answer_text' => $answerText,
                    'answer_data' => $answerData,
                    'grading_status' => $result['status'],
                    'grading_version' => 'structured-v1',
                    'awarded_points' => $awarded,
                    'deterministic_result' => json_encode($result, JSON_THROW_ON_ERROR),
                    'question_snapshot' => json_encode([
                        'id' => $question->id,
                        'version' => $question->question_version ?? '1',
                        'prompt' => $question->prompt,
                        'type' => $question->question_type,
                        'choices' => $delivery->publicChoices($question->choices),
                        'points' => (float) $question->points,
                    ], JSON_THROW_ON_ERROR),
                    'answered_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('grading_recommendations')->insert([
                    'id' => (string) Str::ulid(),
                    'answer_attempt_id' => $answerId,
                    'grader_type' => 'structured_'.$question->question_type,
                    'provider' => null,
                    'model' => null,
                    'prompt_version' => null,
                    'rubric_version' => 'structured-v1',
                    'course_version' => $unit->curriculum_version_id,
                    'status' => $result['status'],
                    'mastery_score' => $result['mastery_score'],
                    'correct_concepts' => json_encode($result['correct_concepts'], JSON_THROW_ON_ERROR),
                    'missing_concepts' => json_encode($result['missing_concepts'], JSON_THROW_ON_ERROR),
                    'misconceptions' => json_encode($result['misconceptions'], JSON_THROW_ON_ERROR),
                    'feedback' => $result['feedback'],
                    'follow_up_question' => null,
                    'requires_remediation' => $result['requires_remediation'],
                    'citations' => json_encode([], JSON_THROW_ON_ERROR),
                    'raw_metadata' => json_encode(['self_study_gate' => true], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $mastery->recordAnswer($enrollment, $question, $answerId, $result);
            }

            $score = $maximum > 0 ? round(($earned / $maximum) * 100, 2) : 0;
            $passed = $score >= (float) $assessment->passing_score;
            $decision = $progression->evaluateAfterGate($enrollment, $unit, $score, $passed, $assessment->assessment_type === 'final_exam');
            DB::table('assessment_attempts')->where('id', $attemptId)->update([
                'status' => 'graded',
                'score' => $score,
                'maximum_score' => 100,
                'passed' => $passed,
                'submitted_at' => now(),
                'grading_summary' => json_encode($decision + ['deterministic_self_study' => true], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Gate mastery check graded automatically.');
    }

    private function storeManualReview(Request $request, LearningUnit $unit, Enrollment $enrollment, Assessment $assessment): RedirectResponse
    {
        $validated = $request->validate(['answers' => ['required', 'array'], 'answers.*' => ['required', 'string', 'min:2', 'max:10000']]);
        abort_if($assessment->questions->pluck('id')->diff(array_keys($validated['answers']))->isNotEmpty(), 422, 'Every gate question requires a response.');

        DB::transaction(function () use ($assessment, $enrollment, $unit, $validated): void {
            $attemptId = (string) Str::ulid();
            $attemptNumber = ((int) DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->max('attempt_number')) + 1;
            DB::table('assessment_attempts')->insert([
                'id' => $attemptId, 'enrollment_id' => $enrollment->id, 'assessment_id' => $assessment->id,
                'attempt_number' => $attemptNumber, 'status' => 'awaiting_review', 'started_at' => now(), 'submitted_at' => now(),
                'grading_summary' => json_encode(['manual_practical_review_required' => true, 'legacy_fallback' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($assessment->questions as $question) {
                DB::table('answer_attempts')->insert([
                    'id' => (string) Str::ulid(), 'assessment_attempt_id' => $attemptId, 'question_id' => $question->id,
                    'question_version' => $question->question_version ?? '1', 'revision' => 1,
                    'answer_text' => $validated['answers'][$question->id], 'grading_status' => 'awaiting_review',
                    'grading_version' => 'manual-review-v1', 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->update(['status' => 'awaiting_review', 'last_activity_at' => now()]);
        });

        return back()->with('success', 'Gate evidence submitted for review.');
    }

    /** @return Collection<int, Question> */
    private function structuredQuestions(Assessment $assessment): Collection
    {
        return $assessment->questions
            ->filter(fn (Question $question): bool => $question->status === 'approved'
                && $question->question_type !== 'free_response'
                && ($question->metadata['completion_role'] ?? null) === 'structured_gate')
            ->values();
    }

    private function normalizeStructuredAnswer(Question $question, mixed $answer): mixed
    {
        $field = "answers.{$question->id}";
        if (in_array($question->question_type, ['single_choice', 'scenario_choice', 'misconception_choice', 'true_false', 'numeric', 'calculation', 'fill_blank'], true)) {
            if (! is_scalar($answer) || trim((string) $answer) === '') {
                throw ValidationException::withMessages([$field => 'Complete this question before submitting.']);
            }

            return trim((string) $answer);
        }
        if (in_array($question->question_type, ['multiple_select', 'matching', 'ordering', 'classification'], true)) {
            if (! is_array($answer) || $answer === []) {
                throw ValidationException::withMessages([$field => 'Complete this question before submitting.']);
            }

            return $answer;
        }

        throw ValidationException::withMessages([$field => 'This deterministic gate question type is not supported.']);
    }

    /** @return array{Enrollment, Assessment} */
    private function context(LearningUnit $unit): array
    {
        $enrollment = Enrollment::query()->where('user_id', request()->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        $progress = UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->firstOrFail();
        abort_if($progress->status === 'locked', 403);
        $assessment = Assessment::query()->with('questions')->where('learning_unit_id', $unit->id)->whereIn('assessment_type', ['gate_exam', 'final_exam'])->firstOrFail();

        return [$enrollment, $assessment];
    }
}
