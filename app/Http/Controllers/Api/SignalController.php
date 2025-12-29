<?php

namespace App\Http\Controllers\Api;

use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\SignalsIndexRequest;
use App\Http\Requests\SignalsLatestRequest;
use App\Http\Resources\SignalResource;
use App\Models\Signal;
use App\Models\Symbol;
use Carbon\CarbonImmutable;

class SignalController extends Controller
{
    public function latest(SignalsLatestRequest $request)
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $timeframe = Timeframe::from($data['timeframe']);

        $signal = Signal::query()
            ->where('symbol_id', $symbol->id)
            ->where('timeframe', $timeframe->value)
            ->orderByDesc('as_of_date')
            ->firstOrFail();

        return (new SignalResource($signal))
            ->additional([
                'meta' => [
                    'symbol' => $symbol->code,
                    'timeframe' => $timeframe->value,
                ],
            ])
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function index(SignalsIndexRequest $request)
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $timeframe = Timeframe::from($data['timeframe']);

        $query = Signal::query()
            ->where('symbol_id', $symbol->id)
            ->where('timeframe', $timeframe->value);

        if (array_key_exists('from', $data) && $data['from'] !== null) {
            $fromDate = CarbonImmutable::parse($data['from'], 'UTC')->toDateString();
            $query->whereDate('as_of_date', '>=', $fromDate);
        }

        if (array_key_exists('to', $data) && $data['to'] !== null) {
            $toDate = CarbonImmutable::parse($data['to'], 'UTC')->toDateString();
            $query->whereDate('as_of_date', '<=', $toDate);
        }

        $signals = $query
            ->orderBy('as_of_date')
            ->get();

        return SignalResource::collection($signals)
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
