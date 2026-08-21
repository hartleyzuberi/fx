<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PracticeHubController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();
        $enrollment = Enrollment::query()->where('user_id', request()->user()->id)->where('status', 'active')->firstOrFail();
        $milestones = collect([
            ['chapter' => 8, 'title' => 'Platform orientation', 'description' => 'Order-entry and platform-literacy drills.'],
            ['chapter' => 17, 'title' => '100-chart progression', 'description' => 'Annotated market-structure and timeframe work.'],
            ['chapter' => 33, 'title' => 'Macro event studies', 'description' => 'Expectations, releases and post-event review.'],
            ['chapter' => 51, 'title' => 'Strategy builder', 'description' => 'A frozen, objective one-page specification.'],
            ['chapter' => 52, 'title' => 'Manual backtesting', 'description' => 'Immutable replay observations with realistic costs.'],
            ['chapter' => 56, 'title' => 'Robustness lab', 'description' => 'Stress costs, parameters, concentration and periods.'],
            ['chapter' => 66, 'title' => 'Serious demo', 'description' => 'Forward testing after strategy evidence survives.'],
        ])->map(function (array $item) use ($enrollment): array {
            $unit = DB::table('learning_units')->where('curriculum_version_id', $enrollment->curriculum_version_id)->where('metadata->chapter', $item['chapter'])->first();
            $status = $unit ? DB::table('unit_progress')->where('enrollment_id', $enrollment->id)->where('learning_unit_id', $unit->id)->value('status') : 'locked';

            return [...$item, 'status' => $status ?? 'locked'];
        });

        $strategies = DB::table('strategy_versions')->where('user_id', $user->id)->orderByDesc('created_at')->get();
        $backtests = DB::table('backtest_runs')->where('user_id', $user->id)->orderByDesc('created_at')->get();
        $programs = DB::table('demo_programs')->where('user_id', $user->id)->orderByDesc('created_at')->get();

        return Inertia::render('practice/index', [
            'milestones' => $milestones,
            'strategies' => $strategies->map(fn ($strategy): array => ['id' => $strategy->id, 'name' => $strategy->name, 'version' => $strategy->version_number, 'status' => $strategy->status, 'frozenAt' => $strategy->frozen_at]),
            'backtests' => $backtests->map(fn ($run): array => ['id' => $run->id, 'strategyId' => $run->strategy_version_id, 'datasetRole' => $run->dataset_role, 'instrument' => $run->instrument, 'timeframe' => $run->timeframe, 'status' => $run->status, 'metrics' => json_decode($run->metrics ?? '{}', true), 'observations' => DB::table('backtest_observations')->where('backtest_run_id', $run->id)->count()]),
            'programs' => $programs->map(fn ($program): array => ['id' => $program->id, 'strategyId' => $program->strategy_version_id, 'status' => $program->status, 'startingBalance' => $program->starting_balance, 'riskPercent' => $program->risk_percent, 'trades' => DB::table('trade_journal_entries')->where('demo_program_id', $program->id)->count(), 'adherence' => (float) (DB::table('trade_journal_entries')->where('demo_program_id', $program->id)->avg('rules_followed') ?? 0) * 100]),
            'robustnessRuns' => DB::table('robustness_runs')->where('user_id', $user->id)->count(),
        ]);
    }
}
