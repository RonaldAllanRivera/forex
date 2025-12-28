<?php

namespace Tests\Unit;

use App\Services\AlphaVantageClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FinnhubClientTest extends TestCase
{
    public function test_it_requires_api_key(): void
    {
        config([
            'services.alphavantage.base_url' => 'https://www.alphavantage.co',
            'services.alphavantage.key' => '',
            'services.alphavantage.cache_ttl_seconds' => 0,
        ]);

        $client = new AlphaVantageClient();

        $this->expectException(RuntimeException::class);
        $client->fxTimeSeries('EUR', 'USD', 'D1', 'compact');
    }

    public function test_it_returns_json_payload(): void
    {
        config([
            'services.alphavantage.base_url' => 'https://www.alphavantage.co',
            'services.alphavantage.key' => 'test-key',
            'services.alphavantage.cache_ttl_seconds' => 0,
        ]);

        Http::fake([
            'https://www.alphavantage.co/query*' => Http::response([
                'Time Series FX (Daily)' => [
                    '2025-01-01' => [
                        '1. open' => '1.1000',
                        '2. high' => '1.1200',
                        '3. low' => '1.0900',
                        '4. close' => '1.1100',
                    ],
                ],
            ], 200),
        ]);

        $client = new AlphaVantageClient();
        $data = $client->fxTimeSeries('EUR', 'USD', 'D1', 'compact');

        $this->assertArrayHasKey('2025-01-01', $data);
    }
}
