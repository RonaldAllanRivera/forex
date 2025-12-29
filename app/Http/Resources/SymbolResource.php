<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SymbolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'provider' => $this->provider,
            'provider_symbol' => $this->provider_symbol,
            'is_active' => $this->is_active,
        ];
    }
}
