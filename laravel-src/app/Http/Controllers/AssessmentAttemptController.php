<?php

namespace App\Http\Controllers;

use App\AI\Services\AiFeature;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Services\Assessment\RubricGrader;
use App\Services\Assessment\SemanticAssessmentService;
use App\Services\Learning\MasteryService;
use App\Services\Learning\ProgressionService;
use App\Services\Tutor\CourseRetriever;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssessmentAttemptController extends Controller
{
    public function store(Request $request, LearningUnit $unit, RubricGrader $grader, SemanticAssessmentService $aiGrader, CourseRetriever $retriever, ProgressionService $progression, MasteryService $mastery): RedirectResponse
    {
        $enrollment = Enrollment::query()->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        $assessment = Assessment::query()->with('questions')->where('learning_unit_id', $unit->id)->firstOrFail();
        $validated = $request->validate(['answers' => ['required', 'array'], 'answers.*' => ['required', 'string', 'min:2', 'max:5000']]);
        $missing = $assessment->questions->pluck('id')->diff(array_keys($validated['answers']));
        abort_if($missing->isNotEmpty(), 422, 'Every question requires an answer.');

        DB::transaction(function () use ($assessment, $enrollment, $unit, $validated, $grader, $aiGrader, $retriever, $progression, $mastery, $request): void {
            $attemptId = (string) Str::ulid();
            $attemptNumber = ((int) DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->max('attempt_number')) + 1;
            DB::table('assessment_attempts')->insert(['id' => $attemptId, 'enrollment_id' => $enrollment->id, 'assessment_id' => $assessment->id, 'attempt_number' => $attemptNumber, 'status' => 'grading', 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $earned = 0.0;
            $maximum = 0.0;
            foreach ($assessment->questions as $question) {
                $answer = $validated['answers'][$question->id];
                $result = $grader->grade($answer, $question->answer_key ?? []);
                $graderType = 'deterministic_rubric';
                if (app(AiFeature::class)->enabled('AI_SEMANTIC_GRADING_ENABLED', (bool) config('ai.semantic_grading_enabled')) && $request->user()->learnerProfile?->ai_tutor_enabled && in_array($result['status'], ['partially_correct', 'incorrect'], true)) {
                    try {
                        $result = $aiGrader->grade($request->user(), $question->prompt, $answer, $question->answer_key ?? [], $retriever->retrieve($unit->id, $question->prompt, 4), $attemptId.':'.$question->id);
                        $graderType = 'ai_semantic';
                    } catch (\Throwable) {
                        // The approved deterministic rubric is the AI-independent equivalent pathway.
                    }
                }
                $awarded = ((float) $question->points) * ($result['mastery_score'] / 100);
                $earned += $awarded;
                $maximum += (float) $question->points;
                $answerId = (string) Str::ulid();
                DB::table('answer_attempts')->insert(['id' => $answerId, 'assessment_attempt_id' => $attemptId, 'question_id' => $question->id, 'revision' => 1, 'answer_text' => $answer, 'grading_status' => $result['status'], 'awarded_points' => $awarded, 'deterministic_result' => json_encode($result, JSON_THROW_ON_ERROR), 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                DB::table('grading_recommendations')->insert(['id' => (string) Str::ulid(), 'answer_attempt_id' => $answerId, 'grader_type' => $graderType, 'provider' => $result['_provider'] ?? null, 'model' => $result['_model'] ?? null, 'prompt_version' => $result['_prompt_version'] ?? null, 'rubric_version' => 'deterministic-v1', 'course_version' => $unit->curriculum_version_id, 'status' => $result['status'], 'mastery_score' => $result['mastery_score'], 'correct_concepts' => json_encode($result['correct_concepts'], JSON_THROW_ON_ERROR), 'missing_concepts' => json_encode($result['missing_concepts'], JSON_THROW_ON_ERROR), 'misconceptions' => json_encode($result['misconceptions'], JSON_THROW_ON_ERROR), 'feedback' => $result['feedback'], 'follow_up_question' => $result['follow_up_question'] ?? null, 'requires_remediation' => $result['requires_remediation'], 'citations' => json_encode($result['citations'] ?? [], JSON_THROW_ON_ERROR), 'raw_metadata' => isset($result['_response_id']) ? json_encode(['response_id' => $result['_response_id'], 'confidence' => $result['confidence'] ?? null], JSON_THROW_ON_ERROR) : null, 'created_at' => now(), 'updated_at' => now()]);
                $mastery->recordAnswer($enrollment, $question, $answerId, $result);
            }
            $score = $maximum > 0 ? round(($earned / $maximum) * 100, 2) : 0;
            $passed = $score >= $assessment->passing_score;
            $decision = $progression->evaluateAfterAssessment($enrollment, $unit, $score, $passed);
            DB::table('assessment_attempts')->where('id', $attemptId)->update(['status' => 'graded', 'score' => $score, 'maximum_score' => 100, 'passed' => $passed, 'submitted_at' => now(), 'grading_summary' => json_encode($decision, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        });

        return to_route('assessments.show', $unit)->with('success', 'Your attempt has been graded and saved.');
    }
}
