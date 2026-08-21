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
            $reasons[] = 'Submit and pass the practical gate review for this stage.';
        }
        $advanced = $reasons === [];
        $next = LearningUnit::query()->where('curriculum_version_id', $unit->curriculum_version_id)->where('unit_type', 'session')->where('position', '>', $unit->position)->orderBy('position')->first();
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
}
