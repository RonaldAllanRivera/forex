<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandleSyncStatus extends Model
{
    protected $fillable = [
        'symbol_id',
        'timeframe',
        'status',
        'queued_at',
        'started_at',
        'finished_at',
        'last_synced_at',
        'last_error',
        'last_upserted',
        'last_stats',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_stats' => 'array',
    ];

    public function symbol()
    {
        return $this->belongsTo(Symbol::class);
    }
}
