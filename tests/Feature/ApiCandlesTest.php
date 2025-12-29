<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCandlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_candles_for_symbol_and_timeframe_ordered_by_time(): void
    {
        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => 1735776000,
            'o' => '1.2000',
            'h' => '1.2100',
            'l' => '1.1900',
            'c' => '1.2050',
            'v' => null,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => 1735689600,
            'o' => '1.1000',
            'h' => '1.1200',
            'l' => '1.0900',
            'c' => '1.1150',
            'v' => null,
        ]);

        $response = $this->getJson('/api/candles?symbol=EURUSD&timeframe=D1');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.t', 1735689600);
        $response->assertJsonPath('data.1.t', 1735776000);
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('meta.timeframe', 'D1');
    }

    public function test_it_validates_required_params(): void
    {
        $response = $this->getJson('/api/candles');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol', 'timeframe']);
    }

    public function test_it_filters_by_from_and_to_dates(): void
    {
        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => 1735689600,
            'o' => '1.1000',
            'h' => '1.1200',
            'l' => '1.0900',
            'c' => '1.1150',
            'v' => null,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => 1735776000,
            'o' => '1.2000',
            'h' => '1.2100',
            'l' => '1.1900',
            'c' => '1.2050',
            'v' => null,
        ]);

        $response = $this->getJson('/api/candles?symbol=EURUSD&timeframe=D1&from=2025-01-02&to=2025-01-02');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.t', 1735776000);
    }
}
