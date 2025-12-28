<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Symbol;
use App\Services\CandleSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CandleSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_upserts_candles_idempotently(): void
    {
        config([
            'services.alphavantage.base_url' => 'https://www.alphavantage.co',
            'services.alphavantage.key' => 'test-key',
            'services.alphavantage.cache_ttl_seconds' => 0,
            'services.alphavantage.lock_ttl_seconds' => 10,
        ]);

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $from = CarbonImmutable::parse('2025-01-01', 'UTC');
        $to = CarbonImmutable::parse('2025-01-10', 'UTC');

        Http::fakeSequence()
            ->push([
                'Time Series FX (Daily)' => [
                    '2025-01-02' => [
                        '1. open' => '1.1100',
                        '2. high' => '1.1300',
                        '3. low' => '1.1000',
                        '4. close' => '1.1200',
                    ],
                    '2025-01-01' => [
                        '1. open' => '1.1000',
                        '2. high' => '1.1200',
                        '3. low' => '1.0900',
                        '4. close' => '1.1100',
                    ],
                ],
            ], 200)
            ->push([
                'Time Series FX (Daily)' => [
                    '2025-01-02' => [
                        '1. open' => '1.1100',
                        '2. high' => '1.1300',
                        '3. low' => '1.1000',
                        '4. close' => '1.1250',
                    ],
                    '2025-01-01' => [
                        '1. open' => '1.1000',
                        '2. high' => '1.1200',
                        '3. low' => '1.0900',
                        '4. close' => '1.1150',
                    ],
                ],
            ], 200);

        $service = app(CandleSyncService::class);

        $count1 = $service->sync($symbol, Timeframe::D1, $from, $to);
        $this->assertSame(2, $count1);
        $this->assertSame(2, Candle::query()->count());

        $count2 = $service->sync($symbol, Timeframe::D1, $from, $to);
        $this->assertSame(2, $count2);
        $this->assertSame(2, Candle::query()->count());

        $updated = Candle::query()->where('symbol_id', $symbol->id)
            ->where('timeframe', Timeframe::D1->value)
            ->where('t', 1735689600)
            ->firstOrFail();

        $this->assertSame('1.115000', $updated->c);
    }

    public function test_it_handles_no_data(): void
    {
        config([
            'services.alphavantage.base_url' => 'https://www.alphavantage.co',
            'services.alphavantage.key' => 'test-key',
            'services.alphavantage.cache_ttl_seconds' => 0,
            'services.alphavantage.lock_ttl_seconds' => 10,
        ]);

        $symbol = Symbol::query()->create([
            'code' => 'USDJPY',
            'provider' => 'alphavantage',
            'provider_symbol' => 'USD/JPY',
            'is_active' => true,
        ]);

        Http::fake([
            'https://www.alphavantage.co/query*' => Http::response([
                'Time Series FX (Weekly)' => [],
            ], 200),
        ]);

        $service = app(CandleSyncService::class);
        $count = $service->sync($symbol, Timeframe::W1);

        $this->assertSame(0, $count);
        $this->assertSame(0, Candle::query()->count());
    }
}
