<?php

namespace App\Jobs;

use App\Enums\Timeframe;
use App\Models\CandleSyncStatus;
use App\Models\Symbol;
use App\Services\CandleSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncCandlesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public readonly int $symbolId,
        public readonly string $timeframe,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {
    }

    public function uniqueId(): string
    {
        return $this->symbolId.':'.$this->timeframe;
    }

    public function handle(CandleSyncService $syncService): void
    {
        $now = CarbonImmutable::now('UTC');

        CandleSyncStatus::query()->updateOrCreate(
            ['symbol_id' => $this->symbolId, 'timeframe' => $this->timeframe],
            [
                'status' => 'running',
                'started_at' => $now,
                'finished_at' => null,
                'last_error' => null,
                'updated_at' => $now,
            ]
        );

        $symbol = Symbol::query()->findOrFail($this->symbolId);
        $timeframe = Timeframe::from($this->timeframe);

        $from = $this->from ? CarbonImmutable::parse($this->from, 'UTC')->startOfDay() : null;
        $to = $this->to ? CarbonImmutable::parse($this->to, 'UTC')->startOfDay() : null;

        try {
            $stats = $syncService->syncWithStats($symbol, $timeframe, $from, $to);

            $doneAt = CarbonImmutable::now('UTC');

            CandleSyncStatus::query()->updateOrCreate(
                ['symbol_id' => $this->symbolId, 'timeframe' => $this->timeframe],
                [
                    'status' => 'succeeded',
                    'finished_at' => $doneAt,
                    'last_synced_at' => $doneAt,
                    'last_upserted' => (int) ($stats['upserted'] ?? 0),
                    'last_stats' => $stats,
                    'last_error' => null,
                    'updated_at' => $doneAt,
                ]
            );
        } catch (Throwable $e) {
            $failedAt = CarbonImmutable::now('UTC');

            CandleSyncStatus::query()->updateOrCreate(
                ['symbol_id' => $this->symbolId, 'timeframe' => $this->timeframe],
                [
                    'status' => 'failed',
                    'finished_at' => $failedAt,
                    'last_error' => $e->getMessage(),
                    'updated_at' => $failedAt,
                ]
            );

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $failedAt = CarbonImmutable::now('UTC');

        CandleSyncStatus::query()->updateOrCreate(
            ['symbol_id' => $this->symbolId, 'timeframe' => $this->timeframe],
            [
                'status' => 'failed',
                'finished_at' => $failedAt,
                'last_error' => $exception->getMessage(),
                'updated_at' => $failedAt,
            ]
        );
    }
}
