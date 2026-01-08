<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeReview extends Model
{
    protected $fillable = [
        'trade_id',
        'candle_as_of_date',
        'review_json',
        'model',
        'prompt_hash',
        'raw_response_json',
    ];

    protected function casts(): array
    {
        return [
            'candle_as_of_date' => 'date',
            'review_json' => 'array',
            'raw_response_json' => 'array',
        ];
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }
}
