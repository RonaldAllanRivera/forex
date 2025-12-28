<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FinnhubClient
{
    public function forexCandles(string $providerSymbol, string $resolution, int $from, int $to): array
    {
        $baseUrl = rtrim((string) config('services.finnhub.base_url', 'https://finnhub.io/api/v1'), '/');
        $token = (string) config('services.finnhub.key');
        $cacheTtlSeconds = (int) config('services.finnhub.cache_ttl_seconds', 60);

        if ($token === '') {
            throw new RuntimeException('Missing Finnhub API key. Set FINNHUB_API_KEY.');
        }

        $cacheKey = 'finnhub:forex:candle:'.sha1(json_encode([
            'base' => $baseUrl,
            'symbol' => $providerSymbol,
            'resolution' => $resolution,
            'from' => $from,
            'to' => $to,
        ]));

        $fetch = function () use ($baseUrl, $providerSymbol, $resolution, $from, $to, $token): array {
            try {
                $response = Http::baseUrl($baseUrl)
                    ->retry(3, 300, throw: false)
                    ->timeout(20)
                    ->acceptJson()
                    ->get('/forex/candle', [
                        'symbol' => $providerSymbol,
                        'resolution' => $resolution,
                        'from' => $from,
                        'to' => $to,
                        'token' => $token,
                    ]);
            } catch (ConnectionException $e) {
                throw new RuntimeException('Finnhub connection error: '.$e->getMessage(), previous: $e);
            }

            if (! $response->successful()) {
                if ($response->status() === 403) {
                    throw new RuntimeException(
                        'Finnhub returned 403 (access denied). Your API key may not have access to this endpoint/symbol on your current plan. '
                        .'Verify access in your Finnhub dashboard and pricing, or try the sandbox base URL if applicable.'
                    );
                }
                try {
                    $response->throw();
                } catch (RequestException $e) {
                    throw new RuntimeException('Finnhub request failed: '.$e->getMessage(), previous: $e);
                }
            }

            $data = $response->json();

            if (! is_array($data) || ! array_key_exists('s', $data)) {
                throw new RuntimeException('Unexpected Finnhub response format.');
            }

            return $data;
        };

        if ($cacheTtlSeconds <= 0) {
            return $fetch();
        }

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), $fetch);
    }
}
