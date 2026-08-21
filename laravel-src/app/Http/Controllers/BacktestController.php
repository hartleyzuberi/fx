<?php

namespace App\Http\Controllers;

use App\Services\Trading\PerformanceMetricsService;
use App\Services\Trading\PracticeAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BacktestController extends Controller
{
    public function store(Request $request, PracticeAccessService $access): RedirectResponse
    {
        $access->assertChapterAvailable($request->user(), 52);
        $data = $request->validate(['strategy_version_id' => ['required', 'string'], 'dataset_role' => ['required', Rule::in(['development', 'test', 'out_of_sample', 'walk_forward'])], 'instrument' => ['required', 'string', 'max:20'], 'timeframe' => ['required', 'string', 'max:20'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after:period_start'], 'cost_assumptions' => ['required', 'array']]);
        abort_unless(DB::table('strategy_versions')->where('id', $data['strategy_version_id'])->where('user_id', $request->user()->id)->where('status', 'frozen')->exists(), 422, 'Backtests require a frozen strategy version.');
        DB::table('backtest_runs')->insert(['id' => (string) Str::ulid(), 'user_id' => $request->user()->id, ...$data, 'cost_assumptions' => json_encode($data['cost_assumptions'], JSON_THROW_ON_ERROR), 'status' => 'in_progress', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Backtest run created.');
    }

    public function addObservation(Request $request, string $run): RedirectResponse
    {
        abort_unless(DB::table('backtest_runs')->where('id', $run)->where('user_id', $request->user()->id)->where('status', 'in_progress')->exists(), 404);
        $data = $request->validate(['observed_at' => ['required', 'date'], 'risk_amount' => ['nullable', 'numeric', 'min:0'], 'r_result' => ['nullable', 'numeric'], 'cost_amount' => ['required', 'numeric', 'min:0'], 'setup_snapshot' => ['required', 'array'], 'notes' => ['nullable', 'string', 'max:10000']]);
        $sequence = ((int) DB::table('backtest_observations')->where('backtest_run_id', $run)->max('sequence')) + 1;
        DB::table('backtest_observations')->insert(['id' => (string) Str::ulid(), 'backtest_run_id' => $run, 'sequence' => $sequence, ...$data, 'setup_snapshot' => json_encode($data['setup_snapshot'], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Observation appended.');
    }

    public function complete(Request $request, string $run, PerformanceMetricsService $metrics): RedirectResponse
    {
        $owned = DB::table('backtest_runs')->where('id', $run)->where('user_id', $request->user()->id)->where('status', 'in_progress')->exists();
        abort_unless($owned, 404);
        $observations = DB::table('backtest_observations')->where('backtest_run_id', $run)->orderBy('sequence')->get()->map(fn ($row): array => (array) $row)->all();
        abort_if(count($observations) === 0, 422, 'At least one observation is required.');
        DB::table('backtest_runs')->where('id', $run)->update(['status' => 'completed', 'metrics' => json_encode($metrics->summarize($observations), JSON_THROW_ON_ERROR), 'updated_at' => now()]);

        return back()->with('success', 'Run frozen with calculated metrics.');
    }
}
