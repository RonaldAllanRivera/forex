<?php

namespace Tests\Unit;

use App\Models\Candle;
use App\Services\StochasticService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class StochasticServiceTest extends TestCase
{
    public function test_it_computes_expected_values_for_small_fixture(): void
    {
        $candles = new Collection([
            new Candle(['t' => 1, 'h' => '10', 'l' => '0', 'c' => '5']),
            new Candle(['t' => 2, 'h' => '12', 'l' => '2', 'c' => '10']),
            new Candle(['t' => 3, 'h' => '11', 'l' => '1', 'c' => '11']),
        ]);

        $service = new StochasticService();
        $out = $service->compute($candles, 3, 1, 1);

        $this->assertCount(1, $out['k']);
        $this->assertCount(1, $out['d']);

        $this->assertSame(3, $out['k'][0]['t']);
        $this->assertSame(3, $out['d'][0]['t']);

        $this->assertEqualsWithDelta(91.6666667, (float) $out['k'][0]['value'], 0.0001);
        $this->assertEqualsWithDelta(91.6666667, (float) $out['d'][0]['value'], 0.0001);
    }
}
