<?php

namespace App\Services;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Symbol;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CandleSyncService
{
    public function __construct(private readonly AlphaVantageClient $alphavantage)
    {
    }

    public function sync(Symbol $symbol, Timeframe $timeframe, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): int
    {
        $lockTtlSeconds = (int) config('services.alphavantage.lock_ttl_seconds', 300);
        $lockKey = sprintf('lock:forex:sync:%s:%s', $symbol->id, $timeframe->value);

        $lock = Cache::lock($lockKey, $lockTtlSeconds);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $to = $to ?? CarbonImmutable::now('UTC');
            $from = $from ?? $this->defaultFrom($symbol, $timeframe, $to);

            if ($from->greaterThanOrEqualTo($to)) {
                return 0;
            }

            if ($symbol->provider !== 'alphavantage') {
                throw new RuntimeException('Unsupported symbol provider for Alpha Vantage sync: '.(string) $symbol->provider);
            }

            $parts = explode('/', strtoupper((string) $symbol->provider_symbol));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                throw new RuntimeException('Invalid Alpha Vantage provider_symbol. Expected format like EUR/USD.');
            }

            [$fromSymbol, $toSymbol] = $parts;

            $outputSize = $from->lessThan($to->subDays(120)) ? 'full' : 'compact';

            $series = $this->alphavantage->fxTimeSeries(
                fromSymbol: $fromSymbol,
                toSymbol: $toSymbol,
                timeframe: $timeframe->value,
                outputSize: $outputSize,
            );

            if ($series === []) {
                return 0;
            }

            $minT = $from->startOfDay()->timestamp;
            $maxT = $to->startOfDay()->timestamp;

            $now = CarbonImmutable::now('UTC');

            $rows = [];

            foreach ($series as $date => $ohlc) {
                if (! is_string($date) || ! is_array($ohlc)) {
                    continue;
                }

                $t = CarbonImmutable::parse($date, 'UTC')->startOfDay()->timestamp;
                if ($t < $minT || $t >= $maxT) {
                    continue;
                }

                $rows[] = [
                    'symbol_id' => $symbol->id,
                    'timeframe' => $timeframe->value,
                    't' => $t,
                    'o' => (string) ($ohlc['o'] ?? ''),
                    'h' => (string) ($ohlc['h'] ?? ''),
                    'l' => (string) ($ohlc['l'] ?? ''),
                    'c' => (string) ($ohlc['c'] ?? ''),
                    'v' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            usort($rows, static fn (array $a, array $b) => ((int) $a['t']) <=> ((int) $b['t']));

            if ($rows === []) {
                return 0;
            }

            return DB::transaction(function () use ($rows): int {
                Candle::upsert(
                    $rows,
                    ['symbol_id', 'timeframe', 't'],
                    ['o', 'h', 'l', 'c', 'v', 'updated_at']
                );

                return count($rows);
            });
        } finally {
            optional($lock)->release();
        }
    }

    private function defaultFrom(Symbol $symbol, Timeframe $timeframe, CarbonImmutable $to): CarbonImmutable
    {
        $latestT = Candle::query()
            ->where('symbol_id', $symbol->id)
            ->where('timeframe', $timeframe->value)
            ->max('t');

        if (is_numeric($latestT)) {
            $overlap = match ($timeframe) {
                Timeframe::D1 => 60 * 60 * 24 * 30,
                Timeframe::W1 => 60 * 60 * 24 * 90,
            };

            return CarbonImmutable::createFromTimestampUTC((int) $latestT)->subSeconds($overlap);
        }

        $backfillDays = match ($timeframe) {
            Timeframe::D1 => 365 * 2,
            Timeframe::W1 => 365 * 5,
        };

        return $to->subDays($backfillDays);
    }
}
