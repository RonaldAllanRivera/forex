<?php

namespace App\Services;

use App\Models\Candle;
use Illuminate\Support\Collection;

class StochasticService
{
    public function compute(Collection $candles, int $kPeriod = 14, int $dPeriod = 3, int $smooth = 3): array
    {
        /** @var array<int, Candle> $items */
        $items = $candles->values()->all();
        $n = count($items);

        if ($n === 0) {
            return ['k' => [], 'd' => []];
        }

        $rawK = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($i < $kPeriod - 1) {
                continue;
            }

            $start = $i - ($kPeriod - 1);
            $highestHigh = null;
            $lowestLow = null;

            for ($j = $start; $j <= $i; $j++) {
                $h = (float) $items[$j]->h;
                $l = (float) $items[$j]->l;
                $highestHigh = $highestHigh === null ? $h : max($highestHigh, $h);
                $lowestLow = $lowestLow === null ? $l : min($lowestLow, $l);
            }

            if ($highestHigh === null || $lowestLow === null) {
                continue;
            }

            $den = $highestHigh - $lowestLow;
            if ($den == 0.0) {
                $rawK[$i] = 50.0;
                continue;
            }

            $close = (float) $items[$i]->c;
            $rawK[$i] = 100.0 * (($close - $lowestLow) / $den);
        }

        $kValues = $this->smaSeries($rawK, $smooth);
        $dValues = $this->smaSeries($kValues, $dPeriod);

        $kOut = [];
        $dOut = [];
        for ($i = 0; $i < $n; $i++) {
            $t = (int) $items[$i]->t;

            if ($kValues[$i] !== null) {
                $kOut[] = ['t' => $t, 'value' => (float) $kValues[$i]];
            }
            if ($dValues[$i] !== null) {
                $dOut[] = ['t' => $t, 'value' => (float) $dValues[$i]];
            }
        }

        return ['k' => $kOut, 'd' => $dOut];
    }

    private function smaSeries(array $values, int $period): array
    {
        $n = count($values);
        if ($n === 0) {
            return [];
        }

        if ($period <= 1) {
            return $values;
        }

        $out = array_fill(0, $n, null);
        $sum = 0.0;
        $count = 0;
        $queue = [];

        for ($i = 0; $i < $n; $i++) {
            $v = $values[$i];
            $queue[] = $v;

            if ($v !== null) {
                $sum += (float) $v;
                $count++;
            }

            if (count($queue) > $period) {
                $old = array_shift($queue);
                if ($old !== null) {
                    $sum -= (float) $old;
                    $count--;
                }
            }

            if (count($queue) === $period && $count === $period) {
                $out[$i] = $sum / $period;
            }
        }

        return $out;
    }
}
