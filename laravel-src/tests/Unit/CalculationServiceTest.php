<?php

namespace Tests\Unit;

use App\Services\Trading\CalculationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculationServiceTest extends TestCase
{
    private CalculationService $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CalculationService;
    }

    public function test_position_size_shows_risk_and_lot_working(): void
    {
        $result = $this->calculator->positionSize(10000, 1, 25, 10);
        $this->assertSame(0.4, $result['result']);
        $this->assertCount(2, $result['working']);
    }

    public function test_expectancy_uses_both_win_and_loss_distributions(): void
    {
        $this->assertSame(0.35, $this->calculator->expectancy(45, 2, 1)['result']);
    }

    public function test_pip_size_changes_for_jpy_quote_pairs(): void
    {
        $this->assertSame(50.0, $this->calculator->pipChange(150.00, 150.50, 'USDJPY')['result']);
        $this->assertSame(50.0, $this->calculator->pipChange(1.1000, 1.1050, 'EURUSD')['result']);
    }

    public function test_drawdown_tracks_peak_to_later_trough(): void
    {
        $this->assertSame(20.0, $this->calculator->maximumDrawdown([10000, 12000, 9600, 11000]));
    }

    public function test_invalid_divisors_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->positionSize(10000, 1, 0, 10);
    }
}
