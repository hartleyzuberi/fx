<?php

namespace App\Services\Assessment;

use App\Services\Trading\CalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GateEvidenceBlueprintService
{
    public function __construct(private readonly CalculationService $calculations) {}

    /** @return array<string, int> */
    public function ensure(): array
    {
        $assignments = 0;
        foreach ($this->assignmentBlueprints() as $blueprint) {
            if ($this->ensureAssignment($blueprint)) {
                $assignments++;
            }
        }

        $positionSizing = $this->ensurePositionSizingDrills();

        return [
            'gate_evidence_assignments_created' => $assignments,
            'gate_d_position_sizing_questions_created' => $positionSizing,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function assignmentBlueprints(): array
    {
        return [
            [
                'chapter' => 8,
                'key' => 'gate_a_platform_and_regulator_competence',
                'title' => 'Gate A evidence: platform and regulator competence',
                'instructions' => 'Complete the platform competence test and record how you verified the current CMA/regulatory source used for broker due diligence. Do not include passwords, account secrets or API credentials.',
                'required' => 1,
                'attachment_required' => false,
            ],
            [
                'chapter' => 17,
                'key' => 'gate_b_annotated_charts',
                'title' => 'Gate B evidence: 100 annotated charts',
                'instructions' => 'Add one chart observation at a time. Each observation must be made without future information and should label structure, relevant support/resistance or breakout context, timeframe role and any indicator used.',
                'required' => 100,
                'attachment_required' => true,
            ],
            [
                'chapter' => 33,
                'key' => 'gate_c_weekly_macro_sheets',
                'title' => 'Gate C evidence: four weekly macro sheets',
                'instructions' => 'Add one completed weekly macro sheet per observation, using primary official sources and distinguishing expectation, actual outcome and revisions where relevant.',
                'required' => 4,
                'attachment_required' => false,
            ],
            [
                'chapter' => 33,
                'key' => 'gate_c_event_studies',
                'title' => 'Gate C evidence: 20 macro event studies',
                'instructions' => 'Add one event study per observation. Record the expectation before release, actual result, revision if any, initial market response, yield/risk context and the lesson from the event.',
                'required' => 20,
                'attachment_required' => false,
            ],
            [
                'chapter' => 58,
                'key' => 'gate_e_strategy_decision',
                'title' => 'Gate E evidence: predeclared strategy decision',
                'instructions' => 'Record the accept, revise or reject decision for the frozen Strategy A evidence package before adding post-hoc excuses. Reference the development, out-of-sample, cost and robustness evidence used.',
                'required' => 1,
                'attachment_required' => false,
            ],
            [
                'chapter' => 65,
                'key' => 'gate_f_review_process',
                'title' => 'Gate F evidence: weekly and monthly review process',
                'instructions' => 'Record the operational weekly/monthly review process, including checklist use, behavioural failure modes, kill switches and the action taken when a rule is broken.',
                'required' => 1,
                'attachment_required' => false,
            ],
            [
                'chapter' => 68,
                'key' => 'gate_g_broker_verification',
                'title' => 'Gate G evidence: broker and entity verification',
                'instructions' => 'Record the broker legal entity, regulator/source checked, verification date and the product/account details relevant to the course. Do not include credentials or sensitive account secrets.',
                'required' => 1,
                'attachment_required' => false,
            ],
            [
                'chapter' => 69,
                'key' => 'gate_g_micro_live_sizing_plan',
                'title' => 'Gate G evidence: 0.25% micro-live sizing plan',
                'instructions' => 'Document how a 0.25% risk-per-trade micro-live size will be calculated for the intended account and instrument assumptions, including what would make the size infeasible.',
                'required' => 1,
                'attachment_required' => false,
            ],
            [
                'chapter' => 71,
                'key' => 'gate_g_descaling_triggers',
                'title' => 'Gate G evidence: return-to-demo and de-scaling triggers',
                'instructions' => 'Write the precommitted conditions that force de-scaling, pausing or a return to demo. The triggers must be operational rather than discretionary after-the-fact excuses.',
                'required' => 1,
                'attachment_required' => false,
            ],
            [
                'chapter' => 73,
                'key' => 'gate_g_operations_continuity',
                'title' => 'Gate G evidence: records, security and continuity plan',
                'instructions' => 'Document the tax/recordkeeping questions that require current professional confirmation, account-security controls, backups, device/network continuity and incident recovery steps.',
                'required' => 1,
                'attachment_required' => false,
            ],
            [
                'chapter' => 90,
                'key' => 'graduation_professional_plan',
                'title' => 'Graduation evidence: professional trading plan',
                'instructions' => 'Submit the final professional trading plan that consolidates strategy scope, risk limits, evidence standards, review cadence, kill switches, broker/operational controls and conditions for future research or scaling.',
                'required' => 1,
                'attachment_required' => false,
            ],
        ];
    }

    /** @param array<string, mixed> $blueprint */
    private function ensureAssignment(array $blueprint): bool
    {
        $unitId = $this->unitId((int) $blueprint['chapter']);
        $exists = DB::table('practice_assignments')
            ->where('learning_unit_id', $unitId)
            ->where('title', $blueprint['title'])
            ->exists();
        if ($exists) {
            return false;
        }

        DB::table('practice_assignments')->insert([
            'id' => (string) Str::ulid(),
            'learning_unit_id' => $unitId,
            'assignment_type' => 'gate_evidence',
            'title' => $blueprint['title'],
            'instructions' => $blueprint['instructions'],
            'requirements' => json_encode([
                'evidence_key' => $blueprint['key'],
                'attachment_required' => (bool) $blueprint['attachment_required'],
                'append_only_observations' => true,
                'canonical_gate_evidence' => true,
            ], JSON_THROW_ON_ERROR),
            'required_observations' => (int) $blueprint['required'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function ensurePositionSizingDrills(): int
    {
        $unitId = $this->unitId(42);
        $gate = DB::table('assessments')
            ->where('learning_unit_id', $unitId)
            ->where('assessment_type', 'gate_exam')
            ->first();
        if (! $gate) {
            return 0;
        }

        $already = DB::table('questions')
            ->where('assessment_id', $gate->id)
            ->where('metadata', 'like', '%gate_d_position_sizing_drill%')
            ->count();
        if ($already >= 50) {
            return 0;
        }

        $position = ((int) DB::table('questions')->where('assessment_id', $gate->id)->max('position')) + 1;
        $created = 0;
        $balances = [1000, 1500, 2000, 2500, 3000, 4000, 5000, 7500, 10000, 12500];
        $riskPercents = [0.25, 0.3, 0.4, 0.5, 0.75];
        $stops = [10, 15, 20, 25, 30, 40, 50, 60, 75, 100];
        $pipValues = [8.5, 9.0, 9.5, 10.0, 10.5];

        for ($index = $already; $index < 50; $index++) {
            $balance = (float) $balances[$index % count($balances)];
            $risk = (float) $riskPercents[($index * 2) % count($riskPercents)];
            $stop = (float) $stops[($index * 3) % count($stops)];
            $pipValue = (float) $pipValues[($index * 4) % count($pipValues)];
            $result = $this->calculations->positionSize($balance, $risk, $stop, $pipValue);

            DB::table('questions')->insert([
                'id' => (string) Str::ulid(),
                'assessment_id' => $gate->id,
                'concept_id' => null,
                'learning_objective_id' => null,
                'rubric_id' => null,
                'source_segment_id' => null,
                'question_type' => 'calculation',
                'difficulty' => 'application',
                'status' => 'approved',
                'question_version' => '1',
                'position' => $position++,
                'prompt' => sprintf(
                    'Position-sizing drill %d of 50: account balance $%s, planned risk %s%%, stop distance %s pips, and pip value $%s per pip per standard lot. What position size in standard lots satisfies the planned risk?',
                    $index + 1,
                    rtrim(rtrim(number_format($balance, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($risk, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($stop, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($pipValue, 2, '.', ''), '0'), '.'),
                ),
                'choices' => null,
                'answer_key' => json_encode([
                    'value' => $result['result'],
                    'tolerance' => 0.0001,
                    'unit' => 'standard lots',
                    'working' => $result['working'],
                ], JSON_THROW_ON_ERROR),
                'explanation' => implode(' ', $result['working']),
                'points' => 1,
                'requires_working' => true,
                'metadata' => json_encode([
                    'completion_role' => 'structured_gate',
                    'evidence_key' => 'gate_d_position_sizing_drill',
                    'critical' => true,
                    'canonical_requirement' => '50+ position-sizing drills with full accuracy after corrections',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }

    private function unitId(int $chapter): string
    {
        $id = DB::table('learning_units')
            ->where('unit_type', 'session')
            ->where('position', $chapter)
            ->value('id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException("Chapter {$chapter} learning unit is not available. Run course ingestion first.");
        }

        return $id;
    }
}
