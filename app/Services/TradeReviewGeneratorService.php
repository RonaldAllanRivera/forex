<?php

namespace App\Services;

use App\Models\Candle;
use App\Models\Trade;
use App\Models\TradeReview;
use Carbon\CarbonImmutable;
use RuntimeException;

class TradeReviewGeneratorService
{
    public function __construct(
        private readonly OpenAiClient $openAi,
        private readonly StochasticService $stochasticService,
        private readonly SupportResistanceService $supportResistanceService,
    ) {
    }

    public function generate(Trade $trade): TradeReview
    {
        $trade->loadMissing('symbol');

        $analysisLimit = match ($trade->timeframe?->value) {
            'D1' => 300,
            'W1' => 260,
            'MN1' => 120,
            default => 300,
        };

        $candles = Candle::query()
            ->where('symbol_id', $trade->symbol_id)
            ->where('timeframe', $trade->timeframe?->value)
            ->orderByDesc('t')
            ->limit($analysisLimit)
            ->get()
            ->sortBy('t')
            ->values();

        if ($candles->isEmpty()) {
            throw new RuntimeException(sprintf('No candles found for %s %s.', (string) $trade->symbol?->code, (string) $trade->timeframe?->value));
        }

        $last = $candles->last();
        $asOfDate = CarbonImmutable::createFromTimestampUTC((int) $last->t)->toDateString();

        $stochK = 14;
        $stochD = 3;
        $stochSmooth = 3;

        $srLookback = match ($trade->timeframe?->value) {
            'W1' => 260,
            'MN1' => 180,
            default => 300,
        };
        $srMaxLevels = 6;
        $srSwing = 2;
        $srClusterPct = match ($trade->timeframe?->value) {
            'W1' => 0.003,
            'MN1' => 0.006,
            default => 0.0015,
        };

        $stoch = $this->stochasticService->compute($candles, $stochK, $stochD, $stochSmooth);
        $sr = $this->supportResistanceService->compute($candles, $srLookback, $srMaxLevels, $srSwing, $srClusterPct);

        $latestK = $stoch['k'] ? (float) end($stoch['k'])['value'] : null;
        $latestD = $stoch['d'] ? (float) end($stoch['d'])['value'] : null;

        $stochState = null;
        if ($latestK !== null) {
            $stochState = $latestK >= 80 ? 'overbought' : ($latestK <= 20 ? 'oversold' : 'neutral');
        }

        $promptCandles = $candles
            ->take(-120)
            ->map(static fn (Candle $c): array => [
                't' => (int) $c->t,
                'o' => (float) $c->o,
                'h' => (float) $c->h,
                'l' => (float) $c->l,
                'c' => (float) $c->c,
            ])
            ->values()
            ->all();

        $payload = [
            'symbol' => $trade->symbol?->code,
            'timeframe' => $trade->timeframe?->value,
            'as_of_date' => $asOfDate,
            'trade' => [
                'side' => $trade->side,
                'entry_price' => (float) $trade->entry_price,
                'stop_loss' => (float) $trade->stop_loss,
                'take_profit' => $trade->take_profit === null ? null : (float) $trade->take_profit,
                'opened_at' => $trade->opened_at?->toISOString(),
                'notes' => $trade->notes,
            ],
            'candles' => $promptCandles,
            'sr_levels' => $sr,
            'stochastic' => [
                'latest' => [
                    'k' => $latestK,
                    'd' => $latestD,
                    'state' => $stochState,
                    'low' => 20,
                    'high' => 80,
                ],
                'params' => [
                    'k' => $stochK,
                    'd' => $stochD,
                    'smooth' => $stochSmooth,
                ],
            ],
        ];

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an experienced forex technical analyst. You review already-opened trades on higher timeframes (D1/W1/MN1). Be conservative, practical, and focus on risk management. Output ONLY valid JSON. Do not include markdown. Do not include extra keys beyond the contract.',
            ],
            [
                'role' => 'user',
                'content' => "Given this market data and current open trade, return a strict JSON object with keys: decision (HOLD|EXIT|ADJUST_STOP|WAIT), confidence (0-100 optional), summary (string), stop_assessment (string), key_levels (array of {type:support|resistance, price:number}), invalidation (string), management_plan (string), risk_note (string).\n\nRules:\n- Base your guidance on market structure and nearby support/resistance first.\n- Evaluate whether the stop loss is structurally placed (beyond a meaningful level) for the given timeframe.\n- If the trade is invalidated by a close beyond a level, recommend EXIT.\n- If the best action is unclear, return WAIT.\n\nMarket + trade JSON:\n".json_encode($payload, JSON_UNESCAPED_SLASHES),
            ],
        ];

        $promptHash = sha1(json_encode($messages));

        $result = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $result = $this->openAi->chatJson($messages);
                $lastException = null;
                break;
            } catch (RuntimeException $e) {
                $lastException = $e;
                if ($attempt === 2) {
                    break;
                }
            }
        }

        if ($result === null) {
            throw $lastException ?: new RuntimeException('OpenAI trade review failed.');
        }

        $parsed = $result['parsed'];

        $decision = $parsed['decision'] ?? null;
        if (! is_string($decision) || ! in_array($decision, ['HOLD', 'EXIT', 'ADJUST_STOP', 'WAIT'], true)) {
            throw new RuntimeException('OpenAI JSON missing or invalid decision.');
        }

        $summary = $parsed['summary'] ?? null;
        if (! is_string($summary) || trim($summary) === '') {
            $summary = 'No summary provided.';
        }

        $reviewJson = [
            'decision' => $decision,
            'confidence' => $parsed['confidence'] ?? null,
            'summary' => $summary,
            'stop_assessment' => $parsed['stop_assessment'] ?? null,
            'key_levels' => $parsed['key_levels'] ?? null,
            'invalidation' => $parsed['invalidation'] ?? null,
            'management_plan' => $parsed['management_plan'] ?? null,
            'risk_note' => $parsed['risk_note'] ?? null,
            'stochastic' => [
                'latest' => $payload['stochastic']['latest'],
            ],
        ];

        return TradeReview::query()->create([
            'trade_id' => $trade->id,
            'candle_as_of_date' => $asOfDate,
            'review_json' => $reviewJson,
            'prompt_hash' => $promptHash,
            'model' => $result['model'],
            'raw_response_json' => $result['raw'],
        ]);
    }
}
