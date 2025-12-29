<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'as_of_date' => $this->as_of_date?->format('Y-m-d'),
            'timeframe' => $this->timeframe?->value,
            'signal' => $this->signal,
            'confidence' => $this->confidence,
            'reason' => $this->reason,
            'levels_json' => $this->levels_json,
            'stoch_json' => $this->stoch_json,
            'model' => $this->model,
        ];
    }
}
