<?php

namespace App\Services\Trading;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReadinessService
{
    /** @return array{ready: bool, predicates: array<string, array{passed: bool, actual: int|string, required: int|string}>} */
    public function seriousDemo(User $user, string $strategyVersionId): array
    {
        $strategy = DB::table('strategy_versions')->where('id', $strategyVersionId)->where('user_id', $user->id)->first();
        $development = $this->completedObservations($user->id, $strategyVersionId, 'development');
        $outOfSample = $this->completedObservations($user->id, $strategyVersionId, 'out_of_sample');
        $robustness = DB::table('robustness_runs')->where('user_id', $user->id)->where('strategy_version_id', $strategyVersionId)->where('conclusion', 'survives')->count();
        $gate = $this->chapterPassed($user->id, 58);
        $predicates = [
            'frozen_strategy' => ['passed' => $strategy?->status === 'frozen', 'actual' => $strategy?->status ?? 'missing', 'required' => 'frozen'],
            'development_observations' => ['passed' => $development >= 30, 'actual' => $development, 'required' => 30],
            'out_of_sample_observations' => ['passed' => $outOfSample >= 30, 'actual' => $outOfSample, 'required' => 30],
            'robustness_tests_survived' => ['passed' => $robustness >= 3, 'actual' => $robustness, 'required' => 3],
            'strategy_evidence_gate' => ['passed' => $gate, 'actual' => $gate ? 'passed' : 'not passed', 'required' => 'passed'],
        ];

        return ['ready' => collect($predicates)->every('passed'), 'predicates' => $predicates];
    }

    /** @return array{ready: bool, predicates: array<string, array{passed: bool, actual: int|float|string, required: int|float|string}>} */
    public function microLive(User $user, string $demoProgramId): array
    {
        $program = DB::table('demo_programs')->where('id', $demoProgramId)->where('user_id', $user->id)->first();
        $trades = DB::table('trade_journal_entries')->where('demo_program_id', $demoProgramId)->where('user_id', $user->id)->orderBy('sequence')->get();
        $adherence = $trades->count() ? round(($trades->where('rules_followed', true)->count() / $trades->count()) * 100, 2) : 0;
        $integrityThirty = $trades->take(30)->count() >= 30 && $trades->take(30)->every(fn ($trade): bool => (bool) $trade->rules_followed);
        $graduation = $this->chapterPassed($user->id, 90);
        $predicates = [
            'active_demo_program' => ['passed' => $program !== null, 'actual' => $program?->status ?? 'missing', 'required' => 'completed'],
            'legitimate_demo_observations' => ['passed' => $trades->count() >= 100, 'actual' => $trades->count(), 'required' => 100],
            'rule_adherence_percent' => ['passed' => $adherence >= 95, 'actual' => $adherence, 'required' => 95],
            'thirty_trade_integrity' => ['passed' => $integrityThirty, 'actual' => $integrityThirty ? 'complete' : 'incomplete', 'required' => 'complete'],
            'graduation_gate' => ['passed' => $graduation, 'actual' => $graduation ? 'passed' : 'not passed', 'required' => 'passed'],
        ];

        return ['ready' => collect($predicates)->every('passed'), 'predicates' => $predicates];
    }

    private function completedObservations(int $userId, string $strategyVersionId, string $role): int
    {
        return DB::table('backtest_observations')->join('backtest_runs', 'backtest_runs.id', '=', 'backtest_observations.backtest_run_id')
            ->where('backtest_runs.user_id', $userId)->where('backtest_runs.strategy_version_id', $strategyVersionId)
            ->where('backtest_runs.dataset_role', $role)->where('backtest_runs.status', 'completed')->count();
    }

    private function chapterPassed(int $userId, int $chapter): bool
    {
        return DB::table('unit_progress')->join('enrollments', 'enrollments.id', '=', 'unit_progress.enrollment_id')
            ->join('learning_units', 'learning_units.id', '=', 'unit_progress.learning_unit_id')
            ->where('enrollments.user_id', $userId)->whereIn('unit_progress.status', ['passed', 'mastered'])
            ->where('learning_units.metadata->chapter', $chapter)->exists();
    }
}
