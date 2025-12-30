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
        $stats = $this->syncWithStats($symbol, $timeframe, $from, $to);

        return (int) ($stats['upserted'] ?? 0);
    }

    /**
     * @return array{inserted:int,updated:int,unchanged:int,upserted:int}
     */
    public function syncWithStats(Symbol $symbol, Timeframe $timeframe, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $lockTtlSeconds = (int) config('services.alphavantage.lock_ttl_seconds', 300);
        $lockKey = sprintf('lock:forex:sync:%s:%s', $symbol->id, $timeframe->value);

        $lock = Cache::lock($lockKey, $lockTtlSeconds);
        if (! $lock->get()) {
            return ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'upserted' => 0];
        }

        try {
            $to = $to ?? CarbonImmutable::now('UTC');
            $from = $from ?? $this->defaultFrom($symbol, $timeframe, $to);

            if ($from->greaterThanOrEqualTo($to)) {
                return ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'upserted' => 0];
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
                return ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'upserted' => 0];
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
                return ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'upserted' => 0];
            }

            $existing = Candle::query()
                ->where('symbol_id', $symbol->id)
                ->where('timeframe', $timeframe->value)
                ->whereIn('t', array_map(static fn (array $r): int => (int) $r['t'], $rows))
                ->get(['t', 'o', 'h', 'l', 'c', 'v'])
                ->keyBy('t');

            $inserted = 0;
            $updated = 0;
            $unchanged = 0;
            $rowsToUpsert = [];

            foreach ($rows as $row) {
                $t = (int) $row['t'];
                $current = $existing->get($t);

                if ($current === null) {
                    $inserted++;
                    $rowsToUpsert[] = $row;
                    continue;
                }

                $o = $this->normalizeDecimal((string) $row['o']);
                $h = $this->normalizeDecimal((string) $row['h']);
                $l = $this->normalizeDecimal((string) $row['l']);
                $c = $this->normalizeDecimal((string) $row['c']);
                $v = $row['v'] === null ? null : $this->normalizeDecimal((string) $row['v']);

                $isSame = (
                    $o === (string) $current->o
                    && $h === (string) $current->h
                    && $l === (string) $current->l
                    && $c === (string) $current->c
                    && $v === ($current->v === null ? null : (string) $current->v)
                );

                if ($isSame) {
                    $unchanged++;
                    continue;
                }

                $updated++;
                $row['o'] = $o;
                $row['h'] = $h;
                $row['l'] = $l;
                $row['c'] = $c;
                $row['v'] = $v;
                $rowsToUpsert[] = $row;
            }

            if ($rowsToUpsert === []) {
                return ['inserted' => $inserted, 'updated' => $updated, 'unchanged' => $unchanged, 'upserted' => 0];
            }

            $upserted = DB::transaction(function () use ($rowsToUpsert): int {
                Candle::upsert(
                    $rowsToUpsert,
                    ['symbol_id', 'timeframe', 't'],
                    ['o', 'h', 'l', 'c', 'v', 'updated_at']
                );

                return count($rowsToUpsert);
            });

            return ['inserted' => $inserted, 'updated' => $updated, 'unchanged' => $unchanged, 'upserted' => $upserted];
        } finally {
            optional($lock)->release();
        }
    }

    private function normalizeDecimal(?string $value): string
    {
        $value = $value ?? '';
        if ($value === '') {
            return '0.000000';
        }

        return number_format((float) $value, 6, '.', '');
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
                Timeframe::MN1 => 60 * 60 * 24 * 365,
            };

            return CarbonImmutable::createFromTimestampUTC((int) $latestT)->subSeconds($overlap);
        }

        $backfillDays = match ($timeframe) {
            Timeframe::D1 => 365 * 2,
            Timeframe::W1 => 365 * 5,
            Timeframe::MN1 => 365 * 15,
        };

        return $to->subDays($backfillDays);
    }
}
