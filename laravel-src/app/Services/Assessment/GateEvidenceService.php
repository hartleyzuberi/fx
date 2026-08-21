<?php

namespace App\Services\Assessment;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GateEvidenceService
{
    /** @return array{gate: string, ready: bool, predicates: array<string, array{passed: bool, actual: mixed, required: mixed}>} */
    public function evaluate(User $user, Assessment $assessment, string $attemptId): array
    {
        $gate = $this->gateCode($assessment);
        $predicates = match ($gate) {
            'A' => $this->gateA($user),
            'B' => $this->gateB($user),
            'C' => $this->gateC($user),
            'D' => $this->gateD($attemptId),
            'E' => $this->gateE($user),
            'F' => $this->gateF($user),
            'G' => $this->gateG($user),
            'FINAL' => $this->finalGate($user, $assessment),
            default => ['recognized_gate' => $this->predicate(false, $gate, 'A-G or FINAL')],
        };

        return [
            'gate' => $gate,
            'ready' => collect($predicates)->every(fn (array $predicate): bool => $predicate['passed']),
            'predicates' => $predicates,
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function gateA(User $user): array
    {
        $platform = $this->observationCount($user->id, 'gate_a_platform_and_regulator_competence');

        return [
            'platform_and_regulator_competence' => $this->predicate($platform >= 1, $platform, '>=1 submitted evidence record'),
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function gateB(User $user): array
    {
        $charts = $this->observationCount($user->id, 'gate_b_annotated_charts');

        return [
            'annotated_charts_without_future_information' => $this->predicate($charts >= 100, $charts, '>=100'),
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function gateC(User $user): array
    {
        $weekly = $this->observationCount($user->id, 'gate_c_weekly_macro_sheets');
        $events = $this->observationCount($user->id, 'gate_c_event_studies');

        return [
            'weekly_macro_sheets' => $this->predicate($weekly >= 4, $weekly, '>=4'),
            'macro_event_studies' => $this->predicate($events >= 20, $events, '>=20'),
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function gateD(string $attemptId): array
    {
        $drillQuestions = DB::table('questions')
            ->where('metadata', 'like', '%gate_d_position_sizing_drill%')
            ->pluck('id');
        $answers = DB::table('answer_attempts')
            ->where('assessment_attempt_id', $attemptId)
            ->whereIn('question_id', $drillQuestions)
            ->get();
        $correct = $answers->where('grading_status', 'correct')->count();
        $total = $answers->count();

        return [
            'position_sizing_drills_completed' => $this->predicate($total >= 50, $total, '>=50'),
            'position_sizing_accuracy_after_corrections' => $this->predicate($total >= 50 && $correct === $total, "{$correct}/{$total}", '100% on >=50 drills'),
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function gateE(User $user): array
    {
        $strategy = DB::table('strategy_versions')
            ->where('user_id', $user->id)
            ->where('status', 'frozen')
            ->orderByDesc('frozen_at')
            ->first();
        if (! $strategy) {
            return [
                'frozen_strategy' => $this->predicate(false, 'missing', 'frozen/versioned Strategy A'),
                'historical_observations' => $this->predicate(false, 0, '>=200 where feasible'),
                'separate_out_of_sample_evidence' => $this->predicate(false, 0, '>0 completed OOS observations'),
                'realistic_cost_assumptions' => $this->predicate(false, 'missing strategy', 'non-empty cost assumptions on completed evidence runs'),
                'robustness_matrix' => $this->predicate(false, [], 'cost stress + parameter sensitivity + concentration/removal evidence'),
                'predeclared_strategy_decision' => $this->predicate(false, 0, '>=1'),
            ];
        }

        $runs = DB::table('backtest_runs')
            ->where('user_id', $user->id)
            ->where('strategy_version_id', $strategy->id)
            ->where('status', 'completed')
            ->get();
        $runIds = $runs->pluck('id');
        $total = DB::table('backtest_observations')->whereIn('backtest_run_id', $runIds)->count();
        $oosRunIds = $runs->whereIn('dataset_role', ['out_of_sample', 'test', 'walk_forward'])->pluck('id');
        $oos = DB::table('backtest_observations')->whereIn('backtest_run_id', $oosRunIds)->count();
        $costsComplete = $runs->isNotEmpty() && $runs->every(function (object $run): bool {
            $assumptions = json_decode((string) $run->cost_assumptions, true);

            return is_array($assumptions) && $assumptions !== [];
        });
        $robustness = DB::table('robustness_runs')
            ->where('user_id', $user->id)
            ->where('strategy_version_id', $strategy->id)
            ->get();
        $types = $robustness->pluck('test_type')->unique()->values();
        $hasCost = $types->contains('cost_stress');
        $hasParameters = $types->contains('parameter_sensitivity');
        $hasConcentration = $types->contains(fn (string $type): bool => in_array($type, ['concentration', 'removal', 'alternate_period', 'alternate_instrument'], true));
        $decision = $this->observationCount($user->id, 'gate_e_strategy_decision');

        return [
            'frozen_strategy' => $this->predicate(true, $strategy->name.' v'.$strategy->version_number, 'frozen/versioned Strategy A'),
            'historical_observations' => $this->predicate($total >= 200, $total, '>=200 where feasible'),
            'separate_out_of_sample_evidence' => $this->predicate($oos > 0, $oos, '>0 completed OOS observations'),
            'realistic_cost_assumptions' => $this->predicate($costsComplete, $costsComplete ? 'present on all completed runs' : 'missing on one or more completed runs', 'non-empty cost assumptions'),
            'robustness_matrix' => $this->predicate($hasCost && $hasParameters && $hasConcentration, $types->all(), 'cost_stress + parameter_sensitivity + concentration/removal family'),
            'predeclared_strategy_decision' => $this->predicate($decision >= 1, $decision, '>=1'),
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function gateF(User $user): array
    {
        $trades = DB::table('trade_journal_entries')
            ->where('user_id', $user->id)
            ->where('mode', 'serious_demo')
            ->orderByDesc('opened_at')
            ->limit(30)
            ->get();
        $lastThirtyRulePerfect = $trades->count() >= 30 && $trades->every(fn (object $trade): bool => (bool) $trade->rules_followed);
        $checklistsComplete = $trades->count() >= 30 && $trades->every(function (object $trade): bool {
            $checklist = json_decode((string) $trade->checklist, true);

            return is_array($checklist) && $checklist !== [];
        });
        $review = $this->observationCount($user->id, 'gate_f_review_process');

        return [
            'journal_entries_for_integrity_challenge' => $this->predicate($trades->count() >= 30, $trades->count(), '>=30 serious-demo journal entries'),
            'thirty_consecutive_rule_perfect_trades' => $this->predicate($lastThirtyRulePerfect, $lastThirtyRulePerfect ? 30 : $trades->where('rules_followed', true)->count(), '30 consecutive'),
            'pre_trade_checklist_consistently_used' => $this->predicate($checklistsComplete, $checklistsComplete ? 'complete for last 30' : 'incomplete', 'complete for last 30'),
            'weekly_monthly_review_process' => $this->predicate($review >= 1, $review, '>=1 operational review record'),
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function gateG(User $user): array
    {
        $program = DB::table('demo_programs')
            ->where('user_id', $user->id)
            ->orderByDesc('started_at')
            ->first();
        $trades = $program
            ? DB::table('trade_journal_entries')
                ->where('user_id', $user->id)
                ->where('demo_program_id', $program->id)
                ->whereNotNull('closed_at')
                ->whereNotNull('r_result')
                ->orderBy('sequence')
                ->get()
            : collect();
        $count = $trades->count();
        $adherence = $count > 0 ? round(($trades->where('rules_followed', true)->count() / $count) * 100, 2) : 0.0;
        $expectancy = $count > 0 ? round((float) $trades->avg('r_result'), 4) : 0.0;
        $broker = $this->observationCount($user->id, 'gate_g_broker_verification');
        $sizing = $this->observationCount($user->id, 'gate_g_micro_live_sizing_plan');
        $triggers = $this->observationCount($user->id, 'gate_g_descaling_triggers');
        $operations = $this->observationCount($user->id, 'gate_g_operations_continuity');

        return [
            'legitimate_closed_demo_trades' => $this->predicate($count >= 100, $count, '>=100'),
            'rule_adherence_percent' => $this->predicate($adherence >= 95, $adherence, '>=95%'),
            'net_expectancy_positive' => $this->predicate($expectancy > 0, $expectancy, '>0R per trade'),
            'broker_entity_verification' => $this->predicate($broker >= 1, $broker, '>=1'),
            'micro_live_025_percent_sizing_plan' => $this->predicate($sizing >= 1, $sizing, '>=1'),
            'return_to_demo_and_descaling_triggers' => $this->predicate($triggers >= 1, $triggers, '>=1'),
            'records_security_and_continuity_plan' => $this->predicate($operations >= 1, $operations, '>=1'),
        ];
    }

    /** @return array<string, array{passed: bool, actual: mixed, required: mixed}> */
    private function finalGate(User $user, Assessment $assessment): array
    {
        $gateAssessments = Assessment::query()
            ->where('assessment_type', 'gate_exam')
            ->pluck('id');
        $passedGateIds = DB::table('assessment_attempts')
            ->join('enrollments', 'enrollments.id', '=', 'assessment_attempts.enrollment_id')
            ->where('enrollments.user_id', $user->id)
            ->whereIn('assessment_attempts.assessment_id', $gateAssessments)
            ->where('assessment_attempts.passed', true)
            ->distinct()
            ->pluck('assessment_attempts.assessment_id');
        $plan = $this->observationCount($user->id, 'graduation_professional_plan');

        return [
            'all_named_gates_passed' => $this->predicate($gateAssessments->count() > 0 && $passedGateIds->count() === $gateAssessments->count(), "{$passedGateIds->count()}/{$gateAssessments->count()}", 'all Gate A-G assessments'),
            'professional_trading_plan' => $this->predicate($plan >= 1, $plan, '>=1 submitted final plan'),
            'final_exam_is_separate_from_evidence_gate' => $this->predicate($assessment->assessment_type === 'final_exam', $assessment->assessment_type, 'final_exam'),
        ];
    }

    private function observationCount(int $userId, string $evidenceKey): int
    {
        $assignmentIds = DB::table('practice_assignments')
            ->where('assignment_type', 'gate_evidence')
            ->get(['id', 'requirements'])
            ->filter(function (object $assignment) use ($evidenceKey): bool {
                $requirements = json_decode((string) $assignment->requirements, true);

                return is_array($requirements) && ($requirements['evidence_key'] ?? null) === $evidenceKey;
            })
            ->pluck('id');

        if ($assignmentIds->isEmpty()) {
            return 0;
        }

        return DB::table('practice_observations')
            ->where('user_id', $userId)
            ->whereIn('practice_assignment_id', $assignmentIds)
            ->where('status', 'submitted')
            ->count();
    }

    private function gateCode(Assessment $assessment): string
    {
        if ($assessment->assessment_type === 'final_exam') {
            return 'FINAL';
        }
        if (preg_match('/\bGate\s+([A-G])\b/i', $assessment->title, $match)) {
            return strtoupper($match[1]);
        }

        $chapter = (int) DB::table('learning_units')->where('id', $assessment->learning_unit_id)->value('position');

        return match (true) {
            $chapter <= 8 => 'A',
            $chapter <= 17 => 'B',
            $chapter <= 33 => 'C',
            $chapter <= 42 => 'D',
            $chapter <= 58 => 'E',
            $chapter <= 65 => 'F',
            $chapter <= 75 => 'G',
            default => 'UNKNOWN',
        };
    }

    /** @return array{passed: bool, actual: mixed, required: mixed} */
    private function predicate(bool $passed, mixed $actual, mixed $required): array
    {
        return compact('passed', 'actual', 'required');
    }
}
