<?php

namespace App\Services\Trading;

class PerformanceMetricsService
{
    /** @param list<array{r_result: float|int|null, cost_amount?: float|int|null}> $observations @return array<string, float|int|null> */
    public function summarize(array $observations): array
    {
        $results = collect($observations)->pluck('r_result')->filter(fn ($value): bool => $value !== null)->map(fn ($value): float => (float) $value)->values();
        $wins = $results->filter(fn (float $value): bool => $value > 0);
        $losses = $results->filter(fn (float $value): bool => $value < 0);
        $grossProfit = $wins->sum();
        $grossLoss = abs($losses->sum());
        $equity = [0.0];
        foreach ($results as $result) {
            $equity[] = end($equity) + $result;
        }

        return [
            'observations' => $results->count(),
            'win_rate_percent' => $results->count() ? round(($wins->count() / $results->count()) * 100, 2) : 0,
            'average_win_r' => $wins->isEmpty() ? null : round($wins->avg(), 4),
            'average_loss_r' => $losses->isEmpty() ? null : round(abs($losses->avg()), 4),
            'net_r' => round($results->sum(), 4),
            'expectancy_r' => $results->isEmpty() ? null : round($results->avg(), 4),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 4) : null,
            'maximum_drawdown_r' => $this->maximumDrawdownR($equity),
            'total_cost_amount' => round(collect($observations)->sum(fn (array $item): float => (float) ($item['cost_amount'] ?? 0)), 4),
        ];
    }

    /** @param list<float|int> $equity */
    private function maximumDrawdownR(array $equity): float
    {
        $peak = (float) ($equity[0] ?? 0);
        $drawdown = 0.0;
        foreach ($equity as $point) {
            $point = (float) $point;
            $peak = max($peak, $point);
            $drawdown = max($drawdown, $peak - $point);
        }

        return round($drawdown, 4);
    }
}
