<?php

namespace App\Http\Controllers;

use App\Services\Trading\PracticeAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RobustnessController extends Controller
{
    public function store(Request $request, PracticeAccessService $access): RedirectResponse
    {
        $access->assertChapterAvailable($request->user(), 56);
        $data = $request->validate([
            'strategy_version_id' => ['required', 'string'],
            'test_type' => ['required', Rule::in(['cost_stress', 'parameter_sensitivity', 'removal', 'concentration', 'alternate_period', 'alternate_instrument'])],
            'parameters' => ['required', 'array'], 'results' => ['required', 'array'],
            'conclusion' => ['required', Rule::in(['survives', 'fragile', 'fails'])],
        ]);
        abort_unless(DB::table('strategy_versions')->where('id', $data['strategy_version_id'])->where('user_id', $request->user()->id)->where('status', 'frozen')->exists(), 422, 'Robustness tests require your frozen strategy version.');
        DB::table('robustness_runs')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $request->user()->id,
            'strategy_version_id' => $data['strategy_version_id'], 'test_type' => $data['test_type'],
            'parameters' => json_encode($data['parameters'], JSON_THROW_ON_ERROR), 'results' => json_encode($data['results'], JSON_THROW_ON_ERROR),
            'conclusion' => $data['conclusion'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Robustness result appended to the evidence record.');
    }
}
