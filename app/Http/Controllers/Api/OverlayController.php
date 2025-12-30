<?php

namespace App\Http\Controllers\Api;

use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\OverlaysShowRequest;
use App\Models\Candle;
use App\Models\Symbol;
use App\Services\StochasticService;
use App\Services\SupportResistanceService;
use Carbon\CarbonImmutable;

class OverlayController extends Controller
{
    public function __construct(
        private readonly StochasticService $stochasticService,
        private readonly SupportResistanceService $supportResistanceService,
    ) {
    }

    public function show(OverlaysShowRequest $request)
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $timeframe = Timeframe::from($data['timeframe']);

        $query = Candle::query()
            ->where('symbol_id', $symbol->id)
            ->where('timeframe', $timeframe->value);

        if (array_key_exists('from', $data) && $data['from'] !== null) {
            $fromT = CarbonImmutable::parse($data['from'], 'UTC')->startOfDay()->timestamp;
            $query->where('t', '>=', $fromT);
        }

        if (array_key_exists('to', $data) && $data['to'] !== null) {
            $toT = CarbonImmutable::parse($data['to'], 'UTC')->startOfDay()->timestamp;
            $query->where('t', '<=', $toT);
        }

        $candles = $query->orderBy('t')->get();

        $stochK = (int) ($data['stoch_k'] ?? 14);
        $stochD = (int) ($data['stoch_d'] ?? 3);
        $stochSmooth = (int) ($data['stoch_smooth'] ?? 3);

        $srLookback = (int) ($data['sr_lookback'] ?? match ($timeframe) {
            Timeframe::W1 => 260,
            Timeframe::MN1 => 180,
            default => 300,
        });
        $srMaxLevels = (int) ($data['sr_max_levels'] ?? 6);
        $srSwing = (int) ($data['sr_swing'] ?? 2);
        $srClusterPct = (float) ($data['sr_cluster_pct'] ?? match ($timeframe) {
            Timeframe::W1 => 0.003,
            Timeframe::MN1 => 0.006,
            default => 0.0015,
        });

        $stoch = $this->stochasticService->compute($candles, $stochK, $stochD, $stochSmooth);
        $sr = $this->supportResistanceService->compute($candles, $srLookback, $srMaxLevels, $srSwing, $srClusterPct);

        return response()->json([
            'data' => [
                'stochastic' => [
                    'k' => $stoch['k'],
                    'd' => $stoch['d'],
                    'params' => [
                        'k' => $stochK,
                        'd' => $stochD,
                        'smooth' => $stochSmooth,
                        'low' => 20,
                        'high' => 80,
                    ],
                ],
                'sr_levels' => $sr,
                'sr_params' => [
                    'lookback' => $srLookback,
                    'max_levels' => $srMaxLevels,
                    'swing' => $srSwing,
                    'cluster_pct' => $srClusterPct,
                ],
            ],
            'meta' => [
                'symbol' => $symbol->code,
                'timeframe' => $timeframe->value,
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
