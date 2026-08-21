<?php

namespace App\Http\Controllers;

use App\Services\Trading\ReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoProgramController extends Controller
{
    public function store(Request $request, ReadinessService $readiness): RedirectResponse
    {
        $data = $request->validate(['strategy_version_id' => ['required', 'string'], 'starting_balance' => ['required', 'numeric', 'gt:0'], 'risk_percent' => ['required', 'numeric', 'gt:0', 'max:2']]);
        $decision = $readiness->seriousDemo($request->user(), $data['strategy_version_id']);
        abort_unless($decision['ready'], 422, 'Serious demo prerequisites are not complete.');
        DB::table('demo_programs')->insert(['id' => (string) Str::ulid(), 'user_id' => $request->user()->id, ...$data, 'status' => 'active', 'required_observations' => 100, 'required_adherence_percent' => 95, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Serious demo program started.');
    }
}
