<?php

namespace App\Http\Controllers;

use App\Services\Trading\ReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TradeJournalController extends Controller
{
    public function store(Request $request, string $program, ReadinessService $readiness): RedirectResponse
    {
        $demo = DB::table('demo_programs')->where('id', $program)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($demo !== null, 404);
        $data = $request->validate([
            'instrument' => ['required', 'string', 'max:20'], 'direction' => ['required', Rule::in(['long', 'short'])],
            'opened_at' => ['required', 'date'], 'closed_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'entry_price' => ['required', 'numeric', 'gt:0'], 'stop_price' => ['required', 'numeric', 'gt:0'],
            'exit_price' => ['nullable', 'numeric', 'gt:0'], 'planned_risk' => ['required', 'numeric', 'gt:0'],
            'r_result' => ['nullable', 'numeric'], 'rules_followed' => ['required', 'boolean'],
            'checklist' => ['required', 'array'], 'context' => ['nullable', 'array'], 'reflection' => ['nullable', 'string', 'max:20000'],
        ]);
        $sequence = ((int) DB::table('trade_journal_entries')->where('demo_program_id', $program)->max('sequence')) + 1;
        DB::table('trade_journal_entries')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'demo_program_id' => $program,
            'strategy_version_id' => $demo->strategy_version_id, 'sequence' => $sequence, 'mode' => 'serious_demo',
            ...$data, 'checklist' => json_encode($data['checklist'], JSON_THROW_ON_ERROR),
            'context' => isset($data['context']) ? json_encode($data['context'], JSON_THROW_ON_ERROR) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $decision = $readiness->microLive($request->user(), $program);
        if (($decision['predicates']['legitimate_demo_observations']['passed'] ?? false) && $demo->status === 'active') {
            DB::table('demo_programs')->where('id', $program)->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
        }
        DB::table('readiness_assessments')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'readiness_type' => 'micro_live',
            'decision' => $decision['ready'] ? 'ready' : 'not_ready',
            'predicate_results' => json_encode($decision['predicates'], JSON_THROW_ON_ERROR),
            'evidence_snapshot' => json_encode(['demo_program_id' => $program, 'trade_sequence' => $sequence], JSON_THROW_ON_ERROR),
            'assessed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Demo observation appended and readiness recalculated.');
    }
}
