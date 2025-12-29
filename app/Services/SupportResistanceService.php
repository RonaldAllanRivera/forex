<?php

namespace App\Services;

use App\Models\Candle;
use Illuminate\Support\Collection;

class SupportResistanceService
{
    public function compute(
        Collection $candles,
        int $lookback = 300,
        int $maxLevels = 8,
        int $swing = 2,
        float $clusterPct = 0.0015,
    ): array {
        $items = $candles->values();
        $n = $items->count();

        if ($n === 0) {
            return [];
        }

        $start = max(0, $n - $lookback);
        $window = $items->slice($start)->values();
        $wn = $window->count();

        if ($wn < ($swing * 2 + 3)) {
            return [];
        }

        $pivots = [];

        for ($i = $swing; $i < $wn - $swing; $i++) {
            /** @var Candle $c */
            $c = $window[$i];

            $h = (float) $c->h;
            $l = (float) $c->l;

            $isHigh = true;
            $isLow = true;

            for ($j = $i - $swing; $j <= $i + $swing; $j++) {
                if ($j === $i) {
                    continue;
                }

                /** @var Candle $cj */
                $cj = $window[$j];
                if ((float) $cj->h >= $h) {
                    $isHigh = false;
                }
                if ((float) $cj->l <= $l) {
                    $isLow = false;
                }

                if (!$isHigh && !$isLow) {
                    break;
                }
            }

            if ($isHigh) {
                $pivots[] = ['price' => $h, 't' => (int) $c->t];
            }
            if (!$isHigh && $isLow) {
                $pivots[] = ['price' => $l, 't' => (int) $c->t];
            }
        }

        if (count($pivots) === 0) {
            return [];
        }

        usort($pivots, static fn (array $a, array $b): int => $a['price'] <=> $b['price']);

        $clusters = [];
        foreach ($pivots as $p) {
            $price = (float) $p['price'];
            $t = (int) $p['t'];

            $placed = false;
            foreach ($clusters as &$cl) {
                $center = (float) $cl['center'];
                $threshold = max(1e-9, abs($center) * $clusterPct);

                if (abs($price - $center) <= $threshold) {
                    $cl['count']++;
                    $cl['center'] = ($center * ($cl['count'] - 1) + $price) / $cl['count'];
                    $cl['last_t'] = max($cl['last_t'], $t);
                    $placed = true;
                    break;
                }
            }
            unset($cl);

            if (!$placed) {
                $clusters[] = ['center' => $price, 'count' => 1, 'last_t' => $t];
            }
        }

        usort($clusters, static function (array $a, array $b): int {
            if ($a['count'] === $b['count']) {
                return $b['last_t'] <=> $a['last_t'];
            }
            return $b['count'] <=> $a['count'];
        });

        $clusters = array_slice($clusters, 0, $maxLevels);

        usort($clusters, static fn (array $a, array $b): int => $a['center'] <=> $b['center']);

        return array_map(static fn (array $cl): array => [
            'price' => (float) $cl['center'],
            'strength' => (int) $cl['count'],
        ], $clusters);
    }
}
