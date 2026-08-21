<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __invoke(string $dataset): StreamedResponse|Response
    {
        $user = request()->user();
        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        [$headers, $rows] = match ($dataset) {
            'notes' => [
                ['notebook', 'entry_type', 'title', 'body', 'physical_task_completed', 'created_at'],
                DB::table('notebook_entries')->where('user_id', $user->id)->orderBy('created_at')->get(['notebook_type', 'entry_type', 'title', 'body', 'physical_task_completed', 'created_at']),
            ],
            'quiz-history' => [
                ['assessment', 'attempt', 'status', 'score', 'passed', 'submitted_at'],
                DB::table('assessment_attempts as attempts')->join('assessments', 'assessments.id', '=', 'attempts.assessment_id')->where('attempts.enrollment_id', $enrollment->id)->orderBy('attempts.created_at')->get(['assessments.title', 'attempts.attempt_number', 'attempts.status', 'attempts.score', 'attempts.passed', 'attempts.submitted_at']),
            ],
            'strategies' => [
                ['name', 'version', 'status', 'specification', 'frozen_at', 'created_at'],
                DB::table('strategy_versions')->where('user_id', $user->id)->orderBy('created_at')->get(['name', 'version_number', 'status', 'specification', 'frozen_at', 'created_at']),
            ],
            'backtests' => [
                ['strategy_id', 'dataset_role', 'instrument', 'timeframe', 'period_start', 'period_end', 'status', 'metrics'],
                DB::table('backtest_runs')->where('user_id', $user->id)->orderBy('created_at')->get(['strategy_version_id', 'dataset_role', 'instrument', 'timeframe', 'period_start', 'period_end', 'status', 'metrics']),
            ],
            'journal' => [
                ['mode', 'instrument', 'direction', 'opened_at', 'closed_at', 'entry_price', 'stop_price', 'exit_price', 'planned_risk', 'r_result', 'rules_followed'],
                DB::table('trade_journal_entries')->where('user_id', $user->id)->orderBy('opened_at')->get(['mode', 'instrument', 'direction', 'opened_at', 'closed_at', 'entry_price', 'stop_price', 'exit_price', 'planned_risk', 'r_result', 'rules_followed']),
            ],
            'progress' => [
                ['unit', 'status', 'mastery_score', 'started_at', 'passed_at', 'last_activity_at'],
                DB::table('unit_progress as progress')->join('learning_units', 'learning_units.id', '=', 'progress.learning_unit_id')->where('progress.enrollment_id', $enrollment->id)->where('learning_units.unit_type', 'session')->orderBy('learning_units.position')->get(['learning_units.title', 'progress.status', 'progress.mastery_score', 'progress.started_at', 'progress.passed_at', 'progress.last_activity_at']),
            ],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($value) => is_bool($value) ? (int) $value : $value, array_values((array) $row)));
            }
            fclose($stream);
        }, "fx-mastery-{$dataset}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
