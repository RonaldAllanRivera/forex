<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Models\Signal;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSignalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_required_params_for_latest(): void
    {
        $this->signIn();

        $response = $this->getJson('/api/signals/latest');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol', 'timeframe']);
    }

    public function test_it_returns_latest_signal(): void
    {
        $this->signIn();

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        Signal::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'as_of_date' => '2025-01-01',
            'signal' => 'WAIT',
            'confidence' => 50,
            'reason' => 'older',
        ]);

        Signal::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'as_of_date' => '2025-01-02',
            'signal' => 'BUY',
            'confidence' => 70,
            'reason' => 'newer',
        ]);

        $response = $this->getJson('/api/signals/latest?symbol=EURUSD&timeframe=D1');

        $response->assertOk();
        $response->assertJsonPath('data.signal', 'BUY');
        $response->assertJsonPath('data.as_of_date', '2025-01-02');
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('meta.timeframe', 'D1');
    }

    public function test_it_returns_signal_history_filtered_by_dates(): void
    {
        $this->signIn();

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        Signal::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'as_of_date' => '2025-01-01',
            'signal' => 'WAIT',
            'confidence' => 50,
            'reason' => 'd1',
        ]);

        Signal::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'as_of_date' => '2025-01-02',
            'signal' => 'BUY',
            'confidence' => 70,
            'reason' => 'd2',
        ]);

        Signal::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'as_of_date' => '2025-01-03',
            'signal' => 'SELL',
            'confidence' => 60,
            'reason' => 'd3',
        ]);

        $response = $this->getJson('/api/signals?symbol=EURUSD&timeframe=D1&from=2025-01-02&to=2025-01-02');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.as_of_date', '2025-01-02');
        $response->assertJsonPath('data.0.signal', 'BUY');
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('meta.timeframe', 'D1');
    }
}
