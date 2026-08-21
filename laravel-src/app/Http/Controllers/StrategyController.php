<?php

namespace App\Http\Controllers;

use App\Services\Trading\PracticeAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StrategyController extends Controller
{
    private const FIELDS = ['market_premise', 'instruments', 'timeframes', 'trading_hours', 'setup_definition', 'market_context', 'entry_trigger', 'stop_logic', 'exit_logic', 'risk_rules', 'news_rules', 'filters', 'invalidation', 'forbidden_conditions', 'minimum_sample_size', 'retirement_rules'];

    public function store(Request $request, PracticeAccessService $access): RedirectResponse
    {
        $access->assertChapterAvailable($request->user(), 51);
        $validated = $request->validate(['name' => ['required', 'string', 'max:120'], 'specification' => ['required', 'array'], ...collect(self::FIELDS)->mapWithKeys(fn (string $field): array => ["specification.{$field}" => ['required']])->all()]);
        $latest = DB::table('strategy_versions')->where('user_id', $request->user()->id)->where('name', $validated['name'])->orderByDesc('version_number')->first();
        $specification = $validated['specification'];
        ksort($specification);
        DB::table('strategy_versions')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'supersedes_id' => $latest?->id,
            'name' => $validated['name'], 'version_number' => ($latest?->version_number ?? 0) + 1, 'status' => 'draft',
            'specification' => json_encode($specification, JSON_THROW_ON_ERROR), 'specification_sha256' => hash('sha256', json_encode($specification, JSON_THROW_ON_ERROR)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'A new immutable strategy version was created.');
    }

    public function freeze(Request $request, string $strategy): RedirectResponse
    {
        $updated = DB::table('strategy_versions')->where('id', $strategy)->where('user_id', $request->user()->id)->where('status', 'draft')->update(['status' => 'frozen', 'frozen_at' => now(), 'updated_at' => now()]);
        abort_unless($updated === 1, 404);

        return back()->with('success', 'Strategy version frozen. Future changes require a new version.');
    }
}
