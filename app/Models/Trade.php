<?php

namespace App\Models;

use App\Enums\Timeframe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends Model
{
    protected $fillable = [
        'user_id',
        'symbol_id',
        'timeframe',
        'side',
        'entry_price',
        'stop_loss',
        'take_profit',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'timeframe' => Timeframe::class,
            'entry_price' => 'decimal:6',
            'stop_loss' => 'decimal:6',
            'take_profit' => 'decimal:6',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TradeReview::class);
    }
}
