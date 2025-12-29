<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            't' => $this->t,
            'timeframe' => $this->timeframe?->value,
            'o' => $this->o,
            'h' => $this->h,
            'l' => $this->l,
            'c' => $this->c,
            'v' => $this->v,
        ];
    }
}
