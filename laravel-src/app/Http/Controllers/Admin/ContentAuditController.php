<?php

namespace App\Http\Controllers\Admin;

use App\AI\Services\AiFeature;
use App\Http\Controllers\Controller;
use App\Services\Curriculum\CoverageAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContentAuditController extends Controller
{
    public function index(CoverageAuditService $coverage): Response
    {
        return Inertia::render('admin/content-audit', [
            'coverage' => $coverage->report(),
            'reviewCounts' => DB::table('content_mappings')->select('review_status', DB::raw('count(*) as total'))->groupBy('review_status')->pluck('total', 'review_status'),
            'documents' => DB::table('source_documents')->join('source_versions', 'source_versions.source_document_id', '=', 'source_documents.id')->get(['source_documents.key', 'source_documents.title', 'source_documents.is_canonical', 'source_versions.version_label', 'source_versions.physical_page_count', 'source_versions.file_sha256']),
            'conflicts' => DB::table('source_conflicts')->where('status', 'open')->latest()->limit(50)->get(),
            'usage' => ['requests' => DB::table('ai_usage_events')->count(), 'inputTokens' => DB::table('ai_usage_events')->sum('input_tokens'), 'outputTokens' => DB::table('ai_usage_events')->sum('output_tokens'), 'estimatedCostUsd' => (float) DB::table('ai_usage_events')->sum('estimated_cost_usd')],
            'flags' => DB::table('feature_flags')->orderBy('key')->get(),
            'pendingGateReviews' => DB::table('assessment_attempts as attempts')
                ->join('assessments', 'assessments.id', '=', 'attempts.assessment_id')
                ->join('enrollments', 'enrollments.id', '=', 'attempts.enrollment_id')
                ->join('users', 'users.id', '=', 'enrollments.user_id')
                ->join('learning_units', 'learning_units.id', '=', 'assessments.learning_unit_id')
                ->where('attempts.status', 'awaiting_review')->whereIn('assessments.assessment_type', ['gate_exam', 'final_exam'])
                ->oldest('attempts.submitted_at')->get(['attempts.id', 'attempts.submitted_at', 'assessments.title', 'assessments.passing_score', 'learning_units.title as unit_title', 'users.name as learner_name']),
        ]);
    }

    public function reviewMapping(Request $request, string $mapping): RedirectResponse
    {
        $data = $request->validate(['review_status' => ['required', Rule::in(['approved', 'rejected', 'needs_revision'])], 'review_note' => ['nullable', 'string', 'max:5000']]);
        $before = DB::table('content_mappings')->where('id', $mapping)->first();
        abort_unless($before !== null, 404);
        DB::table('content_mappings')->where('id', $mapping)->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'mapping_reviewed', 'content_mapping', $mapping, (array) $before, $data);

        return back()->with('success', 'Mapping review saved.');
    }

    public function resolveConflict(Request $request, string $conflict): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['resolved', 'accepted_exception'])], 'resolution' => ['required', 'string', 'min:5', 'max:10000']]);
        $before = DB::table('source_conflicts')->where('id', $conflict)->where('status', 'open')->first();
        abort_unless($before !== null, 404);
        DB::table('source_conflicts')->where('id', $conflict)->update([...$data, 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'source_conflict_resolved', 'source_conflict', $conflict, (array) $before, $data);

        return back()->with('success', 'Conflict resolution recorded.');
    }

    public function updateFlag(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $before = DB::table('feature_flags')->where('key', $key)->first();
        DB::table('feature_flags')->updateOrInsert(['key' => $key], ['id' => $before ? $before->id : (string) Str::ulid(), 'enabled' => $data['enabled'], 'updated_by' => $request->user()->id, 'created_at' => $before ? $before->created_at : now(), 'updated_at' => now()]);
        app(AiFeature::class)->forget($key);
        $this->audit($request, 'feature_flag_updated', 'feature_flag', $key, $before ? (array) $before : null, $data);

        return back()->with('success', 'Feature flag updated.');
    }

    public function reviewGate(Request $request, string $attempt): RedirectResponse
    {
        $data = $request->validate(['score' => ['required', 'numeric', 'between:0,100'], 'feedback' => ['required', 'string', 'min:5', 'max:10000']]);
        $record = DB::table('assessment_attempts as attempts')->join('assessments', 'assessments.id', '=', 'attempts.assessment_id')
            ->where('attempts.id', $attempt)->where('attempts.status', 'awaiting_review')->whereIn('assessments.assessment_type', ['gate_exam', 'final_exam'])
            ->first(['attempts.*', 'assessments.learning_unit_id', 'assessments.passing_score']);
        abort_unless($record !== null, 404);
        $passed = (float) $data['score'] >= (float) $record->passing_score;

        DB::transaction(function () use ($record, $attempt, $data, $passed, $request): void {
            DB::table('assessment_attempts')->where('id', $attempt)->update([
                'status' => 'graded', 'score' => $data['score'], 'maximum_score' => 100, 'passed' => $passed,
                'grading_summary' => json_encode(['feedback' => $data['feedback'], 'reviewed_by' => $request->user()->id], JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
            DB::table('answer_attempts')->where('assessment_attempt_id', $attempt)->update(['grading_status' => 'manually_reviewed', 'updated_at' => now()]);
            DB::table('unit_progress')->where('enrollment_id', $record->enrollment_id)->where('learning_unit_id', $record->learning_unit_id)->update([
                'status' => $passed ? 'passed' : 'needs_review', 'mastery_score' => $data['score'], 'passed_at' => $passed ? now() : null, 'last_activity_at' => now(), 'updated_at' => now(),
            ]);
            if ($passed) {
                $unit = DB::table('learning_units')->where('id', $record->learning_unit_id)->first();
                $next = DB::table('learning_units')->where('curriculum_version_id', $unit->curriculum_version_id)->where('unit_type', 'session')->where('position', '>', $unit->position)->orderBy('position')->first();
                if ($next) {
                    DB::table('unit_progress')->where('enrollment_id', $record->enrollment_id)->where('learning_unit_id', $next->id)->where('status', 'locked')->update(['status' => 'available', 'updated_at' => now()]);
                }
            }
        });
        $this->audit($request, 'gate_reviewed', 'assessment_attempt', $attempt, (array) $record, [...$data, 'passed' => $passed]);

        return back()->with('success', $passed ? 'Gate passed and the next stage unlocked.' : 'Gate review saved; remediation is required.');
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(Request $request, string $event, string $subjectType, string $subjectId, ?array $before, array $after): void
    {
        DB::table('admin_audit_events')->insert(['id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'event_type' => $event, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null, 'after' => json_encode($after, JSON_THROW_ON_ERROR), 'ip_address' => $request->ip(), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
