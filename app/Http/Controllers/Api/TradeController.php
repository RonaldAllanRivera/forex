<?php

namespace App\Http\Controllers\Api;

use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\TradesCurrentRequest;
use App\Http\Requests\TradesReviewRequest;
use App\Http\Resources\TradeResource;
use App\Http\Resources\TradeReviewResource;
use App\Models\Symbol;
use App\Models\Trade;
use App\Models\TradeReview;
use App\Services\TradeReviewGeneratorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TradeController extends Controller
{
    public function current(TradesCurrentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $timeframe = Timeframe::from($data['timeframe']);

        $trade = Trade::query()
            ->with('symbol')
            ->where('user_id', $request->user()->id)
            ->where('symbol_id', $symbol->id)
            ->where('timeframe', $timeframe->value)
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();

        if (! $trade) {
            return response()->json([
                'message' => 'No open trade for this symbol/timeframe.',
            ], 404)->header('Cache-Control', 'no-store');
        }

        $latestReview = TradeReview::query()
            ->with(['trade.symbol'])
            ->where('trade_id', $trade->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => [
                'trade' => (new TradeResource($trade))->toArray($request),
                'latest_review' => $latestReview ? (new TradeReviewResource($latestReview))->toArray($request) : null,
            ],
            'meta' => [
                'symbol' => $symbol->code,
                'timeframe' => $timeframe->value,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function review(TradesReviewRequest $request, TradeReviewGeneratorService $generator)
    {
        $data = $request->validated();

        $symbol = Symbol::query()
            ->where('code', $data['symbol'])
            ->where('is_active', true)
            ->firstOrFail();

        $timeframe = Timeframe::from($data['timeframe']);

        $rateKey = sprintf('trade-review:%s:%s:%s', (string) $request->ip(), $symbol->id, $timeframe->value);
        if (RateLimiter::tooManyAttempts($rateKey, 2)) {
            return response()->json([
                'message' => 'Too many Trade Review requests. Try again shortly.',
            ], 429)->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($rateKey, 60);

        $openedAt = $data['opened_at'] ?? null;
        $openedAt = is_string($openedAt) && $openedAt !== ''
            ? CarbonImmutable::parse($openedAt, 'UTC')
            : null;

        $existingOpen = Trade::query()
            ->where('user_id', $request->user()->id)
            ->where('symbol_id', $symbol->id)
            ->where('timeframe', $timeframe->value)
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();

        $replace = (bool) ($data['replace'] ?? false);
        if ($existingOpen && $replace) {
            $existingOpen->forceFill(['closed_at' => now()])->save();
            $existingOpen = null;
        }

        $trade = $existingOpen;
        if ($trade) {
            $trade->forceFill([
                'side' => $data['side'],
                'entry_price' => $data['entry_price'],
                'stop_loss' => $data['stop_loss'],
                'take_profit' => $data['take_profit'] ?? null,
                'opened_at' => $openedAt,
                'notes' => $data['notes'] ?? null,
            ])->save();
        } else {
            $trade = Trade::query()->create([
                'user_id' => $request->user()->id,
                'symbol_id' => $symbol->id,
                'timeframe' => $timeframe->value,
                'side' => $data['side'],
                'entry_price' => $data['entry_price'],
                'stop_loss' => $data['stop_loss'],
                'take_profit' => $data['take_profit'] ?? null,
                'opened_at' => $openedAt,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        try {
            $review = $generator->generate($trade);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422)->header('Cache-Control', 'no-store');
        }

        return (new TradeReviewResource($review->loadMissing('trade.symbol')))
            ->additional([
                'meta' => [
                    'symbol' => $symbol->code,
                    'timeframe' => $timeframe->value,
                ],
            ])
            ->response()
            ->setStatusCode(201)
            ->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $reviews = TradeReview::query()
            ->with(['trade.symbol'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return TradeReviewResource::collection($reviews)
            ->response()
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, int $id)
    {
        $review = TradeReview::query()
            ->with(['trade.symbol'])
            ->findOrFail($id);

        return (new TradeReviewResource($review))
            ->response()
            ->header('Cache-Control', 'no-store');
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $trade = Trade::query()
            ->with('symbol')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($trade->closed_at === null) {
            $trade->forceFill(['closed_at' => now()])->save();
        }

        return response()->json([
            'data' => (new TradeResource($trade))->toArray($request),
        ])->header('Cache-Control', 'no-store');
    }
}
