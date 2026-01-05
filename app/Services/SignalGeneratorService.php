<?php

namespace App\Services;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Signal;
use App\Models\Symbol;
use Carbon\CarbonImmutable;
use RuntimeException;

class SignalGeneratorService
{
    public function __construct(
        private readonly OpenAiClient $openAi,
        private readonly StochasticService $stochasticService,
        private readonly SupportResistanceService $supportResistanceService,
    ) {
    }

    public function generate(Symbol $symbol, Timeframe $timeframe): Signal
    {
        $analysisLimit = match ($timeframe) {
            Timeframe::D1 => 300,
            Timeframe::W1 => 260,
            Timeframe::MN1 => 120,
        };

        $candles = Candle::query()
            ->where('symbol_id', $symbol->id)
            ->where('timeframe', $timeframe->value)
            ->orderByDesc('t')
            ->limit($analysisLimit)
            ->get()
            ->sortBy('t')
            ->values();

        if ($candles->isEmpty()) {
            throw new RuntimeException(sprintf('No candles found for %s %s.', $symbol->code, $timeframe->value));
        }

        /** @var Candle $last */
        $last = $candles->last();
        $asOfDate = CarbonImmutable::createFromTimestampUTC((int) $last->t)->toDateString();

        $stochK = 14;
        $stochD = 3;
        $stochSmooth = 3;

        $srLookback = match ($timeframe) {
            Timeframe::W1 => 260,
            Timeframe::MN1 => 180,
            default => 300,
        };
        $srMaxLevels = 6;
        $srSwing = 2;
        $srClusterPct = match ($timeframe) {
            Timeframe::W1 => 0.003,
            Timeframe::MN1 => 0.006,
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
            'symbol' => $symbol->code,
            'timeframe' => $timeframe->value,
            'as_of_date' => $asOfDate,
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
                'content' => 'You are an experienced forex technical analyst focused on higher timeframes (D1/W1/MN1). Be conservative and practical. Output ONLY valid JSON. Do not include markdown. Do not include extra keys beyond the contract.',
            ],
            [
                'role' => 'user',
                'content' => "Given this market data, return a strict JSON object with keys: signal (BUY|SELL|WAIT), confidence (0-100 optional), reason (string), key_levels (array of {type:support|resistance, price:number}), stochastic (string), invalidation (string), stress_free_plan (string), risk_note (string).\n\nRules:\n- Use support/resistance and market structure as the primary driver.\n- Identify notable candlestick patterns near key levels (e.g., pin bar, engulfing, inside bar, doji). If none are clear, explicitly say so.\n- If signals conflict or are unclear, return WAIT with lower confidence.\n- key_levels should reflect the most actionable nearby support/resistance levels from sr_levels.\n- invalidation should be framed as candle closes beyond a key level.\n\nMarket data JSON:\n".json_encode($payload, JSON_UNESCAPED_SLASHES),
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
            throw $lastException ?: new RuntimeException('OpenAI signal generation failed.');
        }

        $parsed = $result['parsed'];

        $signalValue = $parsed['signal'] ?? null;
        if (! is_string($signalValue) || ! in_array($signalValue, ['BUY', 'SELL', 'WAIT'], true)) {
            throw new RuntimeException('OpenAI JSON missing or invalid signal.');
        }

        $reason = $parsed['reason'] ?? null;
        if (! is_string($reason) || trim($reason) === '') {
            $reason = 'No reason provided.';
        }

        $confidence = $parsed['confidence'] ?? null;
        if ($confidence !== null) {
            if (is_numeric($confidence)) {
                $confidence = (int) $confidence;
            } else {
                $confidence = null;
            }
        }

        $levelsJson = $parsed['key_levels'] ?? null;
        if ($levelsJson !== null && ! is_array($levelsJson)) {
            $levelsJson = null;
        }

        $stochJson = [
            'latest' => $payload['stochastic']['latest'],
            'interpretation' => $parsed['stochastic'] ?? null,
        ];

        return Signal::query()->updateOrCreate(
            [
                'symbol_id' => $symbol->id,
                'timeframe' => $timeframe->value,
                'as_of_date' => $asOfDate,
            ],
            [
                'signal' => $signalValue,
                'confidence' => $confidence,
                'reason' => $reason,
                'levels_json' => $levelsJson,
                'stoch_json' => $stochJson,
                'prompt_hash' => $promptHash,
                'model' => $result['model'],
                'raw_response_json' => $result['raw'],
            ]
        );
    }
}
