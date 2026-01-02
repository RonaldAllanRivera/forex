<?php

namespace App\Http\Controllers\Api;

use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\SyncCandlesAllRequest;
use App\Http\Requests\SyncCandlesAllStatusRequest;
use App\Jobs\SyncCandlesJob;
use App\Models\CandleSyncStatus;
use App\Models\Symbol;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

class CandleSyncController extends Controller
{
    public function queueAll(SyncCandlesAllRequest $request)
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $rateKey = 'sync-candles-all:'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 2)) {
            return response()->json([
                'message' => 'Too many sync requests. Try again shortly.',
            ], 429)->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($rateKey, 30);

        $now = CarbonImmutable::now('UTC');
        $timeframes = array_map(static fn (Timeframe $t): string => $t->value, Timeframe::cases());

        $statuses = [];

        foreach (Timeframe::cases() as $t) {
            $status = CandleSyncStatus::query()->firstOrCreate(
                ['symbol_id' => $symbol->id, 'timeframe' => $t->value],
                ['status' => 'idle']
            );

            if (!in_array($status->status, ['queued', 'running'], true)) {
                $status->fill([
                    'status' => 'queued',
                    'queued_at' => $now,
                    'started_at' => null,
                    'finished_at' => null,
                    'last_error' => null,
                ])->save();

                SyncCandlesJob::dispatch(
                    $symbol->id,
                    $t->value,
                    $data['from'] ?? null,
                    $data['to'] ?? null,
                );

                $status->refresh();
            }

            $statuses[$t->value] = $this->serializeStatus($status);
        }

        return response()->json([
            'data' => $statuses,
            'meta' => [
                'symbol' => $symbol->code,
                'timeframes' => $timeframes,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function statusAll(SyncCandlesAllStatusRequest $request)
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $timeframes = array_map(static fn (Timeframe $t): string => $t->value, Timeframe::cases());

        $statuses = [];

        foreach (Timeframe::cases() as $t) {
            $status = CandleSyncStatus::query()->firstOrCreate(
                ['symbol_id' => $symbol->id, 'timeframe' => $t->value],
                ['status' => 'idle']
            );

            $statuses[$t->value] = $this->serializeStatus($status);
        }

        return response()->json([
            'data' => $statuses,
            'meta' => [
                'symbol' => $symbol->code,
                'timeframes' => $timeframes,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function serializeStatus(CandleSyncStatus $status): array
    {
        return [
            'status' => $status->status,
            'queued_at' => optional($status->queued_at)->toISOString(),
            'started_at' => optional($status->started_at)->toISOString(),
            'finished_at' => optional($status->finished_at)->toISOString(),
            'last_synced_at' => optional($status->last_synced_at)->toISOString(),
            'last_upserted' => $status->last_upserted,
            'last_error' => $status->last_error,
            'last_stats' => $status->last_stats,
        ];
    }
}
