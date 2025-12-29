<?php

namespace Database\Seeders;

use App\Models\Symbol;
use Illuminate\Database\Seeder;

class SymbolSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $symbols = [
            ['code' => 'EURUSD', 'provider_symbol' => 'EUR/USD'],
            ['code' => 'USDJPY', 'provider_symbol' => 'USD/JPY'],
        ];

        $rows = array_map(static function (array $s) use ($now): array {
            return [
                'code' => $s['code'],
                'provider' => 'alphavantage',
                'provider_symbol' => $s['provider_symbol'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $symbols);

        Symbol::upsert(
            $rows,
            ['code'],
            ['provider', 'provider_symbol', 'is_active', 'updated_at']
        );
    }
}
