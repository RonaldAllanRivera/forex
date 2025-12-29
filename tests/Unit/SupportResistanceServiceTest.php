<?php

namespace Tests\Unit;

use App\Models\Candle;
use App\Services\SupportResistanceService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SupportResistanceServiceTest extends TestCase
{
    public function test_it_detects_swing_high_levels(): void
    {
        $candles = new Collection([
            new Candle(['t' => 1, 'h' => '1', 'l' => '0.5']),
            new Candle(['t' => 2, 'h' => '2', 'l' => '0.8']),
            new Candle(['t' => 3, 'h' => '3', 'l' => '1.0']),
            new Candle(['t' => 4, 'h' => '2', 'l' => '0.9']),
            new Candle(['t' => 5, 'h' => '1.5', 'l' => '0.7']),
        ]);

        $service = new SupportResistanceService();
        $levels = $service->compute($candles, 5, 10, 1, 0.10);

        $this->assertCount(1, $levels);
        $this->assertEqualsWithDelta(3.0, (float) $levels[0]['price'], 0.0001);
        $this->assertSame(1, (int) $levels[0]['strength']);
    }
}
