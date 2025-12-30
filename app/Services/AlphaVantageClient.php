<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AlphaVantageClient
{
    /**
     * @return array<string, array{o:string,h:string,l:string,c:string}>
     */
    public function fxTimeSeries(string $fromSymbol, string $toSymbol, string $timeframe, string $outputSize): array
    {
        $baseUrl = rtrim((string) config('services.alphavantage.base_url', 'https://www.alphavantage.co'), '/');
        $apiKey = (string) config('services.alphavantage.key');
        $cacheTtlSeconds = (int) config('services.alphavantage.cache_ttl_seconds', 900);

        if ($apiKey === '') {
            throw new RuntimeException('Missing Alpha Vantage API key. Set ALPHA_VANTAGE_API_KEY.');
        }

        $function = match ($timeframe) {
            'D1' => 'FX_DAILY',
            'W1' => 'FX_WEEKLY',
            'MN1' => 'FX_MONTHLY',
            default => throw new RuntimeException('Unsupported timeframe for Alpha Vantage: '.$timeframe),
        };

        $cacheKey = 'alphavantage:fx:'.sha1(json_encode([
            'base' => $baseUrl,
            'function' => $function,
            'from' => strtoupper($fromSymbol),
            'to' => strtoupper($toSymbol),
            'outputSize' => $outputSize,
        ]));

        $fetch = function () use ($baseUrl, $apiKey, $function, $fromSymbol, $toSymbol, $outputSize): array {
            try {
                $response = Http::baseUrl($baseUrl)
                    ->retry(3, 300, throw: false)
                    ->timeout(30)
                    ->acceptJson()
                    ->get('/query', [
                        'function' => $function,
                        'from_symbol' => strtoupper($fromSymbol),
                        'to_symbol' => strtoupper($toSymbol),
                        'apikey' => $apiKey,
                        'outputsize' => $outputSize,
                    ]);
            } catch (ConnectionException $e) {
                throw new RuntimeException('Alpha Vantage connection error: '.$e->getMessage(), previous: $e);
            }

            if (! $response->successful()) {
                try {
                    $response->throw();
                } catch (RequestException $e) {
                    throw new RuntimeException('Alpha Vantage request failed: '.$e->getMessage(), previous: $e);
                }
            }

            $data = $response->json();

            if (! is_array($data)) {
                throw new RuntimeException('Unexpected Alpha Vantage response format.');
            }

            if (array_key_exists('Error Message', $data)) {
                throw new RuntimeException('Alpha Vantage error: '.(string) $data['Error Message']);
            }

            if (array_key_exists('Note', $data)) {
                throw new RuntimeException('Alpha Vantage rate limit: '.(string) $data['Note']);
            }

            $seriesKey = match ($function) {
                'FX_DAILY' => 'Time Series FX (Daily)',
                'FX_WEEKLY' => 'Time Series FX (Weekly)',
                'FX_MONTHLY' => 'Time Series FX (Monthly)',
                default => null,
            };

            if ($seriesKey === null) {
                throw new RuntimeException('Unexpected Alpha Vantage function mapping.');
            }

            $series = $data[$seriesKey] ?? [];
            if (! is_array($series)) {
                throw new RuntimeException('Unexpected Alpha Vantage time series format.');
            }

            $out = [];
            foreach ($series as $date => $row) {
                if (! is_string($date) || ! is_array($row)) {
                    continue;
                }

                $o = $row['1. open'] ?? null;
                $h = $row['2. high'] ?? null;
                $l = $row['3. low'] ?? null;
                $c = $row['4. close'] ?? null;

                if (! is_string($o) || ! is_string($h) || ! is_string($l) || ! is_string($c)) {
                    continue;
                }

                $out[$date] = [
                    'o' => $o,
                    'h' => $h,
                    'l' => $l,
                    'c' => $c,
                ];
            }

            return $out;
        };

        if ($cacheTtlSeconds <= 0) {
            return $fetch();
        }

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), $fetch);
    }
}
