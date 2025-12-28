<?php

namespace App\Models;

use App\Enums\Timeframe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candle extends Model
{
    protected $fillable = [
        'symbol_id',
        'timeframe',
        't',
        'o',
        'h',
        'l',
        'c',
        'v',
    ];

    protected function casts(): array
    {
        return [
            'timeframe' => Timeframe::class,
            't' => 'integer',
            'o' => 'decimal:6',
            'h' => 'decimal:6',
            'l' => 'decimal:6',
            'c' => 'decimal:6',
            'v' => 'decimal:6',
        ];
    }

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }
}
