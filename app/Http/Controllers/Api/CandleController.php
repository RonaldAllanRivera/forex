<?php

namespace App\Http\Controllers\Api;

use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\CandlesIndexRequest;
use App\Http\Resources\CandleResource;
use App\Models\Candle;
use App\Models\Symbol;
use Carbon\CarbonImmutable;

class CandleController extends Controller
{
    public function index(CandlesIndexRequest $request)
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

        $candles = $query
            ->orderBy('t')
            ->get();

        return CandleResource::collection($candles)
            ->additional([
                'meta' => [
                    'symbol' => $symbol->code,
                    'timeframe' => $timeframe->value,
                ],
            ])
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }
}
