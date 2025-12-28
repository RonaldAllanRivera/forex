<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Enums\Timeframe;
use App\Models\Symbol;
use App\Services\CandleSyncService;
use Carbon\CarbonImmutable;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('forex:sync-candles {--symbol=} {--timeframe=D1} {--from=} {--to=}', function (CandleSyncService $sync) {
    $symbolCode = $this->option('symbol');
    $timeframe = Timeframe::from((string) $this->option('timeframe'));

    $fromOpt = $this->option('from');
    $toOpt = $this->option('to');

    $from = $fromOpt ? CarbonImmutable::parse((string) $fromOpt, 'UTC') : null;
    $to = $toOpt ? CarbonImmutable::parse((string) $toOpt, 'UTC') : null;

    $query = Symbol::query()->where('is_active', true);
    if (is_string($symbolCode) && $symbolCode !== '') {
        $query->where('code', strtoupper($symbolCode));
    }

    $symbols = $query->orderBy('code')->get();
    if ($symbols->isEmpty()) {
        $this->warn('No active symbols found. Seed the symbols table first.');
        return self::SUCCESS;
    }

    $total = 0;
    foreach ($symbols as $symbol) {
        $count = $sync->sync($symbol, $timeframe, $from, $to);
        $total += $count;
        $this->info(sprintf('%s %s: upserted %d candles', $symbol->code, $timeframe->value, $count));
    }

    $this->info(sprintf('Done. Total upserted: %d', $total));

    return self::SUCCESS;
})->purpose('Sync Forex candles from Alpha Vantage (D1/W1 only)');
