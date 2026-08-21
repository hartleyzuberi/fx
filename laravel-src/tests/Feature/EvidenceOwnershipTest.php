<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvidenceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_learner_cannot_append_to_or_complete_a_backtest_run(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $strategyId = (string) Str::ulid();
        DB::table('strategy_versions')->insert(['id' => $strategyId, 'user_id' => $owner->id, 'name' => 'A', 'version_number' => 1, 'status' => 'frozen', 'specification' => '{}', 'specification_sha256' => hash('sha256', '{}'), 'frozen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $runId = (string) Str::ulid();
        DB::table('backtest_runs')->insert(['id' => $runId, 'user_id' => $owner->id, 'strategy_version_id' => $strategyId, 'dataset_role' => 'development', 'instrument' => 'EURUSD', 'timeframe' => 'H4', 'period_start' => now()->subYear(), 'period_end' => now(), 'cost_assumptions' => '{}', 'status' => 'in_progress', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($intruder)->post(route('backtests.observations.store', $runId), ['observed_at' => now()->toIso8601String(), 'r_result' => 1, 'cost_amount' => 1, 'setup_snapshot' => ['signal' => 'test']])->assertNotFound();
        $this->actingAs($intruder)->post(route('backtests.complete', $runId))->assertNotFound();
        $this->assertDatabaseCount('backtest_observations', 0);
    }

    public function test_serious_demo_cannot_start_by_skipping_evidence_predicates(): void
    {
        $user = User::factory()->create();
        $strategyId = (string) Str::ulid();
        DB::table('strategy_versions')->insert(['id' => $strategyId, 'user_id' => $user->id, 'name' => 'A', 'version_number' => 1, 'status' => 'frozen', 'specification' => '{}', 'specification_sha256' => hash('sha256', '{}'), 'frozen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->post(route('demo-programs.store'), ['strategy_version_id' => $strategyId, 'starting_balance' => 10000, 'risk_percent' => 0.5])->assertUnprocessable();
        $this->assertDatabaseCount('demo_programs', 0);
    }
}
