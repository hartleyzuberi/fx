<?php

namespace App\Http\Controllers;

use App\Services\Trading\CalculationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CalculationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('calculators/index', ['calculation' => null]);
    }

    public function store(Request $request, CalculationService $calculations): Response
    {
        $validated = $request->validate(['calculator' => ['required', Rule::in(['position_size', 'expectancy', 'r_multiple'])]]);
        $type = (string) $validated['calculator'];
        $result = match ($type) {
            'position_size' => $calculations->positionSize(...array_values($request->validate(['balance' => ['required', 'numeric', 'gt:0'], 'risk_percent' => ['required', 'numeric', 'gt:0', 'max:100'], 'stop_pips' => ['required', 'numeric', 'gt:0'], 'pip_value_per_lot' => ['required', 'numeric', 'gt:0']]))),
            'expectancy' => $calculations->expectancy(...array_values($request->validate(['win_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'], 'average_win_r' => ['required', 'numeric', 'min:0'], 'average_loss_r' => ['required', 'numeric', 'min:0']]))),
            'r_multiple' => $calculations->rMultiple(...array_values($request->validate(['profit_or_loss' => ['required', 'numeric'], 'initial_risk' => ['required', 'numeric', 'gt:0']]))),
            default => throw new \LogicException("Unsupported calculator type [{$type}]."),
        };

        return Inertia::render('calculators/index', ['calculation' => ['type' => $type, ...$result]]);
    }
}
