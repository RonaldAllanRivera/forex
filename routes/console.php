<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Enums\Timeframe;
use App\Models\Symbol;
use App\Services\CandleSyncService;
use App\Services\SignalGeneratorService;
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
        $stats = $sync->syncWithStats($symbol, $timeframe, $from, $to);
        $upserted = (int) ($stats['upserted'] ?? 0);
        $total += $upserted;
        $this->info(sprintf(
            '%s %s: inserted=%d updated=%d unchanged=%d upserted=%d',
            $symbol->code,
            $timeframe->value,
            (int) ($stats['inserted'] ?? 0),
            (int) ($stats['updated'] ?? 0),
            (int) ($stats['unchanged'] ?? 0),
            $upserted,
        ));
    }

    $this->info(sprintf('Done. Total upserted: %d', $total));

    return self::SUCCESS;
})->purpose('Sync Forex candles from Alpha Vantage (D1/W1/MN1)');

Artisan::command('forex:generate-signals {--symbol=} {--timeframe=D1}', function (SignalGeneratorService $generator) {
    $symbolCode = $this->option('symbol');
    $timeframe = Timeframe::from((string) $this->option('timeframe'));

    $query = Symbol::query()->where('is_active', true);
    if (is_string($symbolCode) && $symbolCode !== '') {
        $query->where('code', strtoupper($symbolCode));
    }

    $symbols = $query->orderBy('code')->get();
    if ($symbols->isEmpty()) {
        $this->warn('No active symbols found. Seed the symbols table first.');
        return self::SUCCESS;
    }

    $count = 0;
    foreach ($symbols as $symbol) {
        try {
            $signal = $generator->generate($symbol, $timeframe);
            $count++;
            $this->info(sprintf(
                '%s %s %s: %s (%s)',
                $symbol->code,
                $timeframe->value,
                $signal->as_of_date?->format('Y-m-d') ?? 'n/a',
                (string) $signal->signal,
                (string) ($signal->confidence ?? 'n/a'),
            ));
        } catch (\Throwable $e) {
            $this->error(sprintf('%s %s: failed: %s', $symbol->code, $timeframe->value, $e->getMessage()));
        }
    }

    $this->info(sprintf('Done. Generated/updated: %d', $count));

    return self::SUCCESS;
})->purpose('Generate AI signals for active symbols (D1/W1/MN1)');
