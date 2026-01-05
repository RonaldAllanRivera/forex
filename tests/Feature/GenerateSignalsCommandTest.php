<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Signal;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateSignalsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_signal_and_persists_it(): void
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
                'timeframe' => Timeframe::D1->value,
                't' => $baseT + ($i * 86400),
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
                                'signal' => 'BUY',
                                'confidence' => 72,
                                'reason' => 'Test reason',
                                'key_levels' => [
                                    ['type' => 'support', 'price' => 1.2],
                                ],
                                'stochastic' => 'Neutral momentum',
                                'invalidation' => 'Close below support',
                                'stress_free_plan' => 'Wait for pullback',
                                'risk_note' => 'Not financial advice',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('forex:generate-signals --symbol=EURUSD --timeframe=D1')
            ->assertExitCode(0);

        $this->assertSame(1, Signal::query()->count());
        $signal = Signal::query()->firstOrFail();
        $this->assertSame('BUY', (string) $signal->signal);
        $this->assertSame(Timeframe::D1->value, $signal->timeframe->value);
    }

    public function test_it_retries_when_openai_returns_invalid_json_content(): void
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
                'timeframe' => Timeframe::D1->value,
                't' => $baseT + ($i * 86400),
                'o' => (string) (1.1000 + $i * 0.0010),
                'h' => (string) (1.1100 + $i * 0.0010),
                'l' => (string) (1.0900 + $i * 0.0010),
                'c' => (string) (1.1050 + $i * 0.0010),
                'v' => null,
            ]);
        }

        Http::fakeSequence()
            ->push([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{invalid-json}',
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'signal' => 'WAIT',
                                'confidence' => 50,
                                'reason' => 'Recovered on retry',
                                'key_levels' => [],
                                'stochastic' => 'Mixed',
                                'invalidation' => 'n/a',
                                'stress_free_plan' => 'Wait',
                                'risk_note' => 'Not financial advice',
                            ]),
                        ],
                    ],
                ],
            ], 200);

        $this->artisan('forex:generate-signals --symbol=EURUSD --timeframe=D1')
            ->assertExitCode(0);

        $this->assertSame(1, Signal::query()->count());
        $signal = Signal::query()->firstOrFail();
        $this->assertSame('WAIT', (string) $signal->signal);
    }
}
