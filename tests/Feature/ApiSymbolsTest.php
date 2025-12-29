<?php

namespace Tests\Feature;

use App\Models\Symbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSymbolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_active_symbols(): void
    {
        Symbol::query()->create([
            'code' => 'EURUSD',
            'provider' => 'alphavantage',
            'provider_symbol' => 'EUR/USD',
            'is_active' => true,
        ]);

        Symbol::query()->create([
            'code' => 'USDJPY',
            'provider' => 'alphavantage',
            'provider_symbol' => 'USD/JPY',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/symbols');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.code', 'EURUSD');
    }
}
