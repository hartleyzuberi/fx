<?php

namespace Tests\Unit;

use App\Services\Trading\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;

class PerformanceMetricsServiceTest extends TestCase
{
    public function test_it_calculates_a_reproducible_backtest_summary(): void
    {
        $result = (new PerformanceMetricsService)->summarize([
            ['r_result' => 2, 'cost_amount' => 3],
            ['r_result' => -1, 'cost_amount' => 2],
            ['r_result' => 1.5, 'cost_amount' => 3],
            ['r_result' => -1, 'cost_amount' => 2],
        ]);

        $this->assertSame(4, $result['observations']);
        $this->assertSame(50.0, $result['win_rate_percent']);
        $this->assertSame(0.375, $result['expectancy_r']);
        $this->assertSame(1.75, $result['profit_factor']);
        $this->assertSame(1.0, $result['maximum_drawdown_r']);
        $this->assertSame(10.0, $result['total_cost_amount']);
    }
}
