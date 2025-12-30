<?php

namespace Tests\Feature;

use App\Enums\Timeframe;
use App\Models\Candle;
use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiOverlaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_required_params(): void
    {
        $response = $this->getJson('/api/overlays');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['symbol', 'timeframe']);
    }

    public function test_it_returns_stochastic_and_sr_levels(): void
    {
        $symbol = Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $t1 = 1735689600;
        $t2 = 1735776000;
        $t3 = 1735862400;
        $t4 = 1735948800;
        $t5 = 1736035200;

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => $t1,
            'o' => '1.0000',
            'h' => '1.0100',
            'l' => '0.9900',
            'c' => '1.0000',
            'v' => null,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => $t2,
            'o' => '1.0000',
            'h' => '1.0200',
            'l' => '1.0000',
            'c' => '1.0150',
            'v' => null,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => $t3,
            'o' => '1.0200',
            'h' => '1.0300',
            'l' => '1.0050',
            'c' => '1.0200',
            'v' => null,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => $t4,
            'o' => '1.0200',
            'h' => '1.0200',
            'l' => '1.0000',
            'c' => '1.0100',
            'v' => null,
        ]);

        Candle::query()->create([
            'symbol_id' => $symbol->id,
            'timeframe' => Timeframe::D1->value,
            't' => $t5,
            'o' => '1.0100',
            'h' => '1.0100',
            'l' => '0.9900',
            'c' => '1.0180',
            'v' => null,
        ]);

        $response = $this->getJson('/api/overlays?symbol=EURUSD&timeframe=D1&stoch_k=5&stoch_d=1&stoch_smooth=1&sr_lookback=50&sr_swing=1&sr_cluster_pct=0.05&sr_max_levels=10');

        $response->assertOk();
        $response->assertJsonPath('meta.symbol', 'EURUSD');
        $response->assertJsonPath('meta.timeframe', 'D1');

        $payload = $response->json();

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('stochastic', $payload['data']);
        $this->assertArrayHasKey('sr_levels', $payload['data']);

        $this->assertIsArray($payload['data']['stochastic']['k']);
        $this->assertIsArray($payload['data']['stochastic']['d']);

        $this->assertCount(1, $payload['data']['stochastic']['k']);
        $this->assertCount(1, $payload['data']['stochastic']['d']);

        $kPoint = $payload['data']['stochastic']['k'][0];
        $dPoint = $payload['data']['stochastic']['d'][0];

        $this->assertSame($t5, $kPoint['t']);
        $this->assertSame($t5, $dPoint['t']);

        $this->assertEqualsWithDelta(70.0, (float) $kPoint['value'], 0.0001);
        $this->assertEqualsWithDelta(70.0, (float) $dPoint['value'], 0.0001);

        $sr = $payload['data']['sr_levels'];
        $this->assertIsArray($sr);
        $this->assertCount(1, $sr);
        $this->assertEqualsWithDelta(1.03, (float) $sr[0]['price'], 0.0001);
        $this->assertSame(1, (int) $sr[0]['strength']);
    }

    public function test_it_returns_mn1_default_overlay_params(): void
    {
        Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/overlays?symbol=EURUSD&timeframe=MN1');

        $response->assertOk();
        $response->assertJsonPath('meta.timeframe', 'MN1');
        $response->assertJsonPath('data.sr_params.lookback', 180);
        $response->assertJsonPath('data.sr_params.cluster_pct', 0.006);
        $response->assertJsonPath('data.stochastic.params.k', 14);
        $response->assertJsonPath('data.stochastic.params.d', 3);
        $response->assertJsonPath('data.stochastic.params.smooth', 3);
    }
}
