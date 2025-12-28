<?php

namespace App\Models;

use App\Enums\Timeframe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signal extends Model
{
    protected $fillable = [
        'symbol_id',
        'timeframe',
        'as_of_date',
        'signal',
        'confidence',
        'reason',
        'levels_json',
        'stoch_json',
        'prompt_hash',
        'model',
        'raw_response_json',
    ];

    protected function casts(): array
    {
        return [
            'timeframe' => Timeframe::class,
            'as_of_date' => 'date',
            'confidence' => 'integer',
            'levels_json' => 'array',
            'stoch_json' => 'array',
            'raw_response_json' => 'array',
        ];
    }

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }
}
