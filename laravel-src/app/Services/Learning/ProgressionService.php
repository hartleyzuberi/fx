<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\UnitProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProgressionService
{
    /** @return array{advanced: bool, reasons: list<string>, next_unit_slug: string|null} */
    public function evaluateAfterAssessment(Enrollment $enrollment, LearningUnit $unit, float $score, bool $assessmentPassed): array
    {
        $reasons = [];
        if (! $assessmentPassed) {
            $reasons[] = 'The chapter quiz score is below the required threshold.';
        }
        $notebookCount = DB::table('notebook_entries')->where('user_id', $enrollment->user_id)->where('learning_unit_id', $unit->id)->where('notebook_type', 'concept')->count();
        if ($notebookCount < 1) {
            $reasons[] = 'Add at least one own-words entry to the Concept Notebook.';
        }
        $gateRequired = DB::table('assessments')->where('learning_unit_id', $unit->id)->whereIn('assessment_type', ['gate_exam', 'final_exam'])->exists();
        if ($reasons === [] && $gateRequired) {
            $reasons[] = 'Complete the structured gate mastery check for this stage.';
        }
        $advanced = $reasons === [];
        $next = $this->nextSession($unit);
        $currentProgress = UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->firstOrFail();
        $from = $currentProgress->status;
        $to = $advanced ? 'passed' : ($assessmentPassed ? ($gateRequired && $notebookCount >= 1 ? 'awaiting_gate' : 'in_progress') : 'needs_review');
        $currentProgress->update(['status' => $to, 'mastery_score' => $score, 'passed_at' => $advanced ? now() : null, 'last_activity_at' => now()]);
        if ($advanced && $next) {
            UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $next->id)->where('status', 'locked')->update(['status' => 'available']);
        }
        DB::table('progression_events')->insert([
            'id' => (string) Str::ulid(), 'enrollment_id' => $enrollment->id, 'learning_unit_id' => $unit->id,
            'event_type' => 'assessment_evaluated', 'from_status' => $from, 'to_status' => $to,
            'rule_evidence' => json_encode(['score' => $score, 'assessment_passed' => $assessmentPassed, 'concept_notebook_entries' => $notebookCount, 'gate_required' => $gateRequired, 'reasons' => $reasons], JSON_THROW_ON_ERROR),
            'actor_type' => 'system', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['advanced' => $advanced, 'reasons' => $reasons, 'next_unit_slug' => $advanced ? $next?->slug : null];
    }

    /** @return array{advanced: bool, reasons: list<string>, next_unit_slug: string|null, course_completed: bool} */
    public function evaluateAfterGate(Enrollment $enrollment, LearningUnit $unit, float $score, bool $passed, bool $finalExam = false): array
    {
        $reasons = $passed ? [] : ['The gate score is below the required threshold.'];
        $currentProgress = UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->firstOrFail();
        $from = $currentProgress->status;
        $to = $passed ? 'passed' : 'needs_review';
        $next = $this->nextSession($unit);

        $currentProgress->update([
            'status' => $to,
            'mastery_score' => $score,
            'passed_at' => $passed ? now() : null,
            'last_activity_at' => now(),
        ]);

        if ($passed && $next) {
            UnitProgress::query()->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $next->id)->where('status', 'locked')->update(['status' => 'available']);
        }

        $courseCompleted = $passed && $finalExam && $next === null;
        if ($courseCompleted) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
        }

        DB::table('progression_events')->insert([
            'id' => (string) Str::ulid(),
            'enrollment_id' => $enrollment->id,
            'learning_unit_id' => $unit->id,
            'event_type' => $finalExam ? 'final_exam_evaluated' : 'gate_evaluated',
            'from_status' => $from,
            'to_status' => $to,
            'rule_evidence' => json_encode([
                'score' => $score,
                'gate_passed' => $passed,
                'final_exam' => $finalExam,
                'course_completed' => $courseCompleted,
                'reasons' => $reasons,
            ], JSON_THROW_ON_ERROR),
            'actor_type' => 'system',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'advanced' => $passed,
            'reasons' => $reasons,
            'next_unit_slug' => $passed ? $next?->slug : null,
            'course_completed' => $courseCompleted,
        ];
    }

    private function nextSession(LearningUnit $unit): ?LearningUnit
    {
        return LearningUnit::query()
            ->where('curriculum_version_id', $unit->curriculum_version_id)
            ->where('unit_type', 'session')
            ->where('position', '>', $unit->position)
            ->orderBy('position')
            ->first();
    }
}
