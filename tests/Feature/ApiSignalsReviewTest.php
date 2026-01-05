<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Signal;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiSignalsReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_required_params_for_review(): void
    {
        $response = $this->postJson('/api/signals/review');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol', 'timeframe']);
    }

    public function test_it_generates_signal_and_returns_resource(): void
    {
        config([
            'services.openai.base_url' => 'https://api.openai.com/v1',
            'services.openai.key' => 'test-openai-key',
            'services.openai.model' => 'test-model',
            'services.openai.timeout_seconds' => 5,
        ]);

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $baseT = 1735689600; // 2025-01-01 UTC
        for ($i = 0; $i < 25; $i++) {
            Candle::query()->create([
                'symbol_id' => $symbol->id,
                'timeframe' => Timeframe::W1->value,
                't' => $baseT + ($i * 7 * 86400),
                'o' => (string) (1.1000 + $i * 0.0010),
                'h' => (string) (1.1100 + $i * 0.0010),
                'l' => (string) (1.0900 + $i * 0.0010),
                'c' => (string) (1.1050 + $i * 0.0010),
                'v' => null,
            ]);
        }

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'signal' => 'WAIT',
                                'confidence' => 55,
                                'reason' => 'At resistance; no clean reversal candle yet.',
                                'key_levels' => [
                                    ['type' => 'resistance', 'price' => 1.2000],
                                    ['type' => 'support', 'price' => 1.1500],
                                ],
                                'stochastic' => 'Neutral / mixed momentum.',
                                'invalidation' => 'Weekly close above resistance.',
                                'stress_free_plan' => 'Wait for rejection candle at resistance or pullback to support.',
                                'risk_note' => 'Not financial advice.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/signals/review', [
            'symbol' => 'EURUSD',
            'timeframe' => 'W1',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.signal', 'WAIT');
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('meta.timeframe', 'W1');

        $this->assertSame(1, Signal::query()->count());
        $this->assertDatabaseHas('signals', [
            'symbol_id' => $symbol->id,
            'timeframe' => 'W1',
            'signal' => 'WAIT',
        ]);
    }
}
