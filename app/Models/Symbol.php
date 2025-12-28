<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Symbol extends Model
{
    protected $fillable = [
        'code',
        'provider',
        'provider_symbol',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function candles(): HasMany
    {
        return $this->hasMany(Candle::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }
}
