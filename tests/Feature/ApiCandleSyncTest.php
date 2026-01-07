<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Jobs\SyncCandlesJob;
use App\Models\CandleSyncStatus;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiCandleSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_all_timeframe_statuses_defaulting_to_idle(): void
    {
        $this->signIn();

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/sync-candles/status-all?symbol=EURUSD');

        $response->assertOk();
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('data.D1.status', 'idle');
        $response->assertJsonPath('data.W1.status', 'idle');
        $response->assertJsonPath('data.MN1.status', 'idle');

        foreach ([Timeframe::D1->value, Timeframe::W1->value, Timeframe::MN1->value] as $tf) {
            $this->assertDatabaseHas('candle_sync_statuses', [
                'symbol_id' => $symbol->id,
                'timeframe' => $tf,
            ]);
        }
    }

    public function test_it_queues_all_timeframes(): void
    {
        $this->signIn();

        Queue::fake();

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $response = $this->withCsrfToken()->postJson('/api/sync-candles/all', [
            'symbol' => 'EURUSD',
        ]);

        $response->assertOk();
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('meta.timeframes.0', 'D1');

        foreach ([Timeframe::D1->value, Timeframe::W1->value, Timeframe::MN1->value] as $tf) {
            $this->assertDatabaseHas('candle_sync_statuses', [
                'symbol_id' => $symbol->id,
                'timeframe' => $tf,
                'status' => 'queued',
            ]);
        }

        Queue::assertPushed(SyncCandlesJob::class, 3);
        Queue::assertPushed(SyncCandlesJob::class, function (SyncCandlesJob $job) use ($symbol) {
            return $job->symbolId === $symbol->id && $job->timeframe === Timeframe::D1->value;
        });
        Queue::assertPushed(SyncCandlesJob::class, function (SyncCandlesJob $job) use ($symbol) {
            return $job->symbolId === $symbol->id && $job->timeframe === Timeframe::W1->value;
        });
        Queue::assertPushed(SyncCandlesJob::class, function (SyncCandlesJob $job) use ($symbol) {
            return $job->symbolId === $symbol->id && $job->timeframe === Timeframe::MN1->value;
        });
    }

    public function test_it_returns_all_timeframe_statuses(): void
    {
        $this->signIn();

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        CandleSyncStatus::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'status' => 'succeeded',
        ]);
        CandleSyncStatus::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::W1->value,
            'status' => 'running',
        ]);
        CandleSyncStatus::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::MN1->value,
            'status' => 'failed',
            'last_error' => 'Boom',
        ]);

        $response = $this->getJson('/api/sync-candles/status-all?symbol=EURUSD');

        $response->assertOk();
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('data.D1.status', 'succeeded');
        $response->assertJsonPath('data.W1.status', 'running');
        $response->assertJsonPath('data.MN1.status', 'failed');
        $response->assertJsonPath('data.MN1.last_error', 'Boom');
    }
}
