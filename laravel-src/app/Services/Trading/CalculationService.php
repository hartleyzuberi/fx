<?php

namespace App\Services\Trading;

use InvalidArgumentException;

class CalculationService
{
    /** @return array{result: float, working: list<string>, unit: string} */
    public function positionSize(float $balance, float $riskPercent, float $stopPips, float $pipValuePerLot): array
    {
        $this->positive($balance, $stopPips, $pipValuePerLot);
        if ($riskPercent <= 0 || $riskPercent > 100) {
            throw new InvalidArgumentException('Risk percent must be greater than zero and no more than 100.');
        }
        $riskAmount = $balance * ($riskPercent / 100);
        $lots = $riskAmount / ($stopPips * $pipValuePerLot);

        return ['result' => round($lots, 4), 'unit' => 'standard lots', 'working' => ["Risk amount = {$balance} × {$riskPercent}% = ".round($riskAmount, 2), "Position size = {$riskAmount} ÷ ({$stopPips} pips × {$pipValuePerLot} per pip) = ".round($lots, 4).' lots']];
    }

    /** @return array{result: float, working: list<string>, unit: string} */
    public function pipChange(float $entry, float $exit, string $pair): array
    {
        $pipSize = str_ends_with(strtoupper($pair), 'JPY') ? 0.01 : 0.0001;
        $pips = ($exit - $entry) / $pipSize;

        return ['result' => round($pips, 1), 'unit' => 'pips', 'working' => ['Pip size for '.strtoupper($pair)." = {$pipSize}", "Pip change = ({$exit} − {$entry}) ÷ {$pipSize} = ".round($pips, 1)]];
    }

    /** @return array{result: float, working: list<string>, unit: string} */
    public function rMultiple(float $profitOrLoss, float $initialRisk): array
    {
        $this->positive($initialRisk);
        $value = $profitOrLoss / $initialRisk;

        return ['result' => round($value, 3), 'unit' => 'R', 'working' => ["R multiple = outcome ÷ initial risk = {$profitOrLoss} ÷ {$initialRisk} = ".round($value, 3).'R']];
    }

    /** @return array{result: float, working: list<string>, unit: string} */
    public function expectancy(float $winRatePercent, float $averageWinR, float $averageLossR): array
    {
        if ($winRatePercent < 0 || $winRatePercent > 100 || $averageWinR < 0 || $averageLossR < 0) {
            throw new InvalidArgumentException('Rates must be valid percentages and average outcomes cannot be negative.');
        }
        $winRate = $winRatePercent / 100;
        $lossRate = 1 - $winRate;
        $value = ($winRate * $averageWinR) - ($lossRate * $averageLossR);

        return ['result' => round($value, 4), 'unit' => 'R per trade', 'working' => ["Loss rate = 1 − {$winRate} = {$lossRate}", "Expectancy = ({$winRate} × {$averageWinR}R) − ({$lossRate} × {$averageLossR}R) = ".round($value, 4).'R']];
    }

    public function profitFactor(float $grossProfit, float $grossLoss): float
    {
        $this->positive($grossLoss);

        return round($grossProfit / $grossLoss, 4);
    }

    /** @param list<float|int> $equityCurve */
    public function maximumDrawdown(array $equityCurve): float
    {
        if ($equityCurve === []) {
            throw new InvalidArgumentException('An equity curve is required.');
        }
        $peak = (float) $equityCurve[0];
        $maximum = 0.0;
        foreach ($equityCurve as $equity) {
            $equity = (float) $equity;
            $peak = max($peak, $equity);
            if ($peak > 0) {
                $maximum = max($maximum, (($peak - $equity) / $peak) * 100);
            }
        }

        return round($maximum, 4);
    }

    public function marginRequired(float $notionalValue, float $leverage): float
    {
        $this->positive($notionalValue, $leverage);

        return round($notionalValue / $leverage, 2);
    }

    /** @param list<float|int> $riskPercents */
    public function totalOpenRisk(array $riskPercents): float
    {
        foreach ($riskPercents as $risk) {
            if ($risk < 0) {
                throw new InvalidArgumentException('Open risk cannot be negative.');
            }
        }

        return round(array_sum($riskPercents), 4);
    }

    private function positive(float ...$values): void
    {
        if (collect($values)->contains(fn (float $value): bool => $value <= 0)) {
            throw new InvalidArgumentException('Values used as a divisor or exposure must be greater than zero.');
        }
    }
}
