<?php

namespace App\Http\Controllers\Api;

use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\SignalsIndexRequest;
use App\Http\Requests\SignalsLatestRequest;
use App\Http\Requests\SignalsReviewRequest;
use App\Http\Resources\SignalResource;
use App\Models\Signal;
use App\Models\Symbol;
use App\Services\SignalGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

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

    public function review(SignalsReviewRequest $request, SignalGeneratorService $generator)
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $timeframe = Timeframe::from($data['timeframe']);

        $rateKey = sprintf('signals-review:%s:%s:%s', (string) $request->ip(), $symbol->id, $timeframe->value);
        if (RateLimiter::tooManyAttempts($rateKey, 2)) {
            return response()->json([
                'message' => 'Too many AI Review requests. Try again shortly.',
            ], 429)->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($rateKey, 45);

        try {
            $signal = $generator->generate($symbol, $timeframe);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422)->header('Cache-Control', 'no-store');
        }

        return (new SignalResource($signal))
            ->additional([
                'meta' => [
                    'symbol' => $symbol->code,
                    'timeframe' => $timeframe->value,
                ],
            ])
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
