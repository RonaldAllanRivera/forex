<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Symbol;
use App\Models\Trade;
use App\Models\TradeReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTradesReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_required_params_for_review(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $response = $this->withCsrfToken()->postJson('/api/trades/review');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol', 'timeframe', 'side', 'entry_price', 'stop_loss']);
    }

    public function test_it_creates_trade_and_review_and_returns_resource(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

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
                                'decision' => 'HOLD',
                                'confidence' => 60,
                                'summary' => 'Structure still valid; manage risk.',
                                'stop_assessment' => 'Stop is reasonable but could be tightened after confirmation.',
                                'key_levels' => [
                                    ['type' => 'resistance', 'price' => 1.2000],
                                    ['type' => 'support', 'price' => 1.1500],
                                ],
                                'invalidation' => 'Weekly close below support.',
                                'management_plan' => 'Hold while above support; consider moving stop after bullish close.',
                                'risk_note' => 'Not financial advice.',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withCsrfToken()->postJson('/api/trades/review', [
            'symbol' => 'EURUSD',
            'timeframe' => 'W1',
            'side' => 'BUY',
            'entry_price' => 1.1800,
            'stop_loss' => 1.1600,
            'take_profit' => 1.2200,
            'notes' => 'Swing entry after breakout retest.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.review_json.decision', 'HOLD');
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('meta.timeframe', 'W1');

        $this->assertSame(1, Trade::query()->count());
        $this->assertSame(1, TradeReview::query()->count());
    }

    public function test_it_reuses_existing_open_trade_by_default_and_creates_new_review_snapshots(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

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

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => ['content' => json_encode(['decision' => 'HOLD', 'summary' => 'First review.'])],
                ]],
            ], 200)
            ->push([
                'choices' => [[
                    'message' => ['content' => json_encode(['decision' => 'WAIT', 'summary' => 'Second review.'])],
                ]],
            ], 200);

        $first = $this->withCsrfToken()->postJson('/api/trades/review', [
            'symbol' => 'EURUSD',
            'timeframe' => 'W1',
            'side' => 'BUY',
            'entry_price' => 1.1800,
            'stop_loss' => 1.1600,
        ]);
        $first->assertCreated();

        $second = $this->withCsrfToken()->postJson('/api/trades/review', [
            'symbol' => 'EURUSD',
            'timeframe' => 'W1',
            'side' => 'BUY',
            'entry_price' => 1.1800,
            'stop_loss' => 1.1650,
        ]);
        $second->assertCreated();
        $second->assertJsonPath('data.review_json.decision', 'WAIT');

        $this->assertSame(1, Trade::query()->count());
        $this->assertSame(2, TradeReview::query()->count());
        $this->assertNull(Trade::query()->first()?->closed_at);
    }

    public function test_it_can_replace_open_trade_when_requested(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

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
                'choices' => [[
                    'message' => ['content' => json_encode(['decision' => 'HOLD', 'summary' => 'Ok.'])],
                ]],
            ], 200),
        ]);

        $this->withCsrfToken()->postJson('/api/trades/review', [
            'symbol' => 'EURUSD',
            'timeframe' => 'W1',
            'side' => 'BUY',
            'entry_price' => 1.1800,
            'stop_loss' => 1.1600,
        ])->assertCreated();

        $this->withCsrfToken()->postJson('/api/trades/review', [
            'symbol' => 'EURUSD',
            'timeframe' => 'W1',
            'side' => 'BUY',
            'entry_price' => 1.2000,
            'stop_loss' => 1.1800,
            'replace' => true,
        ])->assertCreated();

        $this->assertSame(2, Trade::query()->count());
        $closed = Trade::query()->orderBy('id')->first();
        $open = Trade::query()->orderByDesc('id')->first();
        $this->assertNotNull($closed?->closed_at);
        $this->assertNull($open?->closed_at);
    }

    public function test_it_returns_current_open_trade_and_latest_review(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $trade = Trade::query()->create([
            'user_id' => $admin->id,
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::W1->value,
            'side' => 'SELL',
            'entry_price' => 1.1700,
            'stop_loss' => 1.1900,
            'take_profit' => 1.0300,
            'opened_at' => null,
            'closed_at' => null,
            'notes' => 'Test open trade',
        ]);

        $review = TradeReview::query()->create([
            'trade_id' => $trade->id,
            'candle_as_of_date' => '2026-01-01',
            'review_json' => ['decision' => 'WAIT'],
            'model' => 'test',
            'prompt_hash' => 'abc',
            'raw_response_json' => ['ok' => true],
        ]);

        $response = $this->getJson('/api/trades/current?symbol=EURUSD&timeframe=W1');
        $response->assertOk();
        $response->assertJsonPath('data.trade.id', $trade->id);
        $response->assertJsonPath('data.latest_review.id', $review->id);
    }

    public function test_it_can_close_an_open_trade(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $trade = Trade::query()->create([
            'user_id' => $admin->id,
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'side' => 'BUY',
            'entry_price' => 1.1000,
            'stop_loss' => 1.0900,
            'take_profit' => null,
            'opened_at' => null,
            'closed_at' => null,
            'notes' => null,
        ]);

        $res = $this->withCsrfToken()->postJson('/api/trades/'.$trade->id.'/close');
        $res->assertOk();

        $trade->refresh();
        $this->assertNotNull($trade->closed_at);
    }

    public function test_it_lists_and_shows_trade_reviews(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $trade = Trade::query()->create([
            'user_id' => $admin->id,
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            'side' => 'BUY',
            'entry_price' => 1.1000,
            'stop_loss' => 1.0900,
            'take_profit' => null,
            'opened_at' => null,
            'closed_at' => null,
            'notes' => null,
        ]);

        $review = TradeReview::query()->create([
            'trade_id' => $trade->id,
            'candle_as_of_date' => '2026-01-01',
            'review_json' => ['decision' => 'WAIT'],
            'model' => 'test',
            'prompt_hash' => 'abc',
            'raw_response_json' => ['ok' => true],
        ]);

        $indexResponse = $this->getJson('/api/trades');
        $indexResponse->assertOk();
        $indexResponse->assertJsonPath('data.0.id', $review->id);

        $showResponse = $this->getJson('/api/trades/'.$review->id);
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.id', $review->id);
    }
}
