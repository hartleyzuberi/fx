<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GateAssessmentController extends Controller
{
    public function show(LearningUnit $unit): Response
    {
        [$enrollment, $assessment] = $this->context($unit);
        $latest = DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->latest('attempt_number')->first();

        return Inertia::render('assessment/gate', [
            'unit' => ['slug' => $unit->slug, 'title' => $unit->title],
            'assessment' => [
                'title' => $assessment->title,
                'passingScore' => $assessment->passing_score,
                'questions' => $assessment->questions->map(fn ($question): array => ['id' => $question->id, 'position' => $question->position, 'prompt' => $question->prompt]),
            ],
            'latestAttempt' => $latest ? ['status' => $latest->status, 'score' => $latest->score, 'passed' => $latest->passed, 'summary' => json_decode($latest->grading_summary ?? '{}', true)] : null,
        ]);
    }

    public function store(Request $request, LearningUnit $unit): RedirectResponse
    {
        [$enrollment, $assessment] = $this->context($unit);
        abort_unless(DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->whereIn('assessment_id', Assessment::query()->where('learning_unit_id', $unit->id)->where('assessment_type', 'chapter_quiz')->pluck('id'))->where('passed', true)->exists(), 403, 'Pass the chapter mastery check before submitting this gate.');
        $validated = $request->validate(['answers' => ['required', 'array'], 'answers.*' => ['required', 'string', 'min:2', 'max:10000']]);
        abort_if($assessment->questions->pluck('id')->diff(array_keys($validated['answers']))->isNotEmpty(), 422, 'Every gate question requires a response.');

        DB::transaction(function () use ($assessment, $enrollment, $unit, $validated): void {
            $attemptId = (string) Str::ulid();
            $attemptNumber = ((int) DB::table('assessment_attempts')->where('enrollment_id', $enrollment->id)->where('assessment_id', $assessment->id)->max('attempt_number')) + 1;
            DB::table('assessment_attempts')->insert([
                'id' => $attemptId, 'enrollment_id' => $enrollment->id, 'assessment_id' => $assessment->id,
                'attempt_number' => $attemptNumber, 'status' => 'awaiting_review', 'started_at' => now(), 'submitted_at' => now(),
                'grading_summary' => json_encode(['manual_practical_review_required' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($assessment->questions as $question) {
                DB::table('answer_attempts')->insert([
                    'id' => (string) Str::ulid(), 'assessment_attempt_id' => $attemptId, 'question_id' => $question->id,
                    'revision' => 1, 'answer_text' => $validated['answers'][$question->id], 'grading_status' => 'awaiting_review',
                    'answered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->update(['status' => 'awaiting_review', 'last_activity_at' => now()]);
        });

        return back()->with('success', 'Gate evidence submitted for review.');
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
