<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use App\Services\Assessment\QuestionDeliveryService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function show(LearningUnit $unit, QuestionDeliveryService $delivery): Response
    {
        $user = request()->user();
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        abort_unless($unit->curriculum_version_id === $enrollment->curriculum_version_id, 404);
        abort_if(UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->value('status') === 'locked', 403);
        $assessment = Assessment::query()->with('questions')->where('learning_unit_id', $unit->id)->where('assessment_type', 'chapter_quiz')->firstOrFail();
        $latest = DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->where('status', 'graded')->latest('attempt_number')->first();
        $results = $latest ? DB::table('answer_attempts')->join('questions', 'questions.id', '=', 'answer_attempts.question_id')->where('assessment_attempt_id', $latest->id)->orderBy('questions.position')->get(['questions.prompt', 'questions.explanation', 'answer_attempts.answer_text', 'answer_attempts.answer_data', 'answer_attempts.grading_status', 'answer_attempts.awarded_points', 'answer_attempts.deterministic_result']) : collect();

        return Inertia::render('assessment/show', [
            'unit' => ['slug' => $unit->slug, 'title' => $unit->title],
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'passingScore' => $assessment->passing_score,
                'questions' => $assessment->questions->map(function ($question) use ($delivery, $user): array {
                    $type = $delivery->effectiveType($user, $question);

                    return [
                        'id' => $question->id,
                        'position' => $question->position,
                        'type' => $type,
                        'prompt' => $question->prompt,
                        'points' => (float) $question->points,
                        'choices' => $delivery->publicChoices($delivery->effectiveChoices($user, $question)),
                        'requiresWorking' => (bool) $question->requires_working,
                        'structuredFallback' => $delivery->usesStructuredVariant($user, $question),
                    ];
                })->values(),
            ],
            'latestAttempt' => $latest ? [
                'score' => (float) $latest->score,
                'passed' => (bool) $latest->passed,
                'summary' => json_decode($latest->grading_summary ?? '{}', true),
                'results' => $results->map(fn ($result): array => [
                    'prompt' => $result->prompt,
                    'answer' => $result->answer_text ?? $this->displayAnswerData($result->answer_data),
                    'status' => $result->grading_status,
                    'feedback' => json_decode($result->deterministic_result ?? '{}', true)['feedback'] ?? '',
                    'explanation' => $result->explanation,
                ]),
            ] : null,
        ]);
    }

    private function displayAnswerData(?string $answerData): string
    {
        if (! $answerData) {
            return '';
        }
        $decoded = json_decode($answerData, true);
        if (! is_array($decoded)) {
            return (string) $decoded;
        }

        return implode(', ', array_map(
            fn (mixed $key, mixed $value): string => is_string($key) ? "{$key}: {$value}" : (string) $value,
            array_keys($decoded),
            array_values($decoded),
        ));
    }
}
