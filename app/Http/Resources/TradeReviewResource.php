<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trade_id' => $this->trade_id,
            'candle_as_of_date' => $this->candle_as_of_date?->format('Y-m-d'),
            'review_json' => $this->review_json,
            'model' => $this->model,
            'generated_at' => $this->created_at?->toISOString(),
            'trade' => $this->whenLoaded('trade', function (): array {
                return (new TradeResource($this->trade->loadMissing('symbol')))->toArray(request());
            }),
        ];
    }
}
