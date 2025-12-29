<?php

namespace App\Http\Requests;

use App\Enums\Timeframe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverlaysShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $timeframes = array_map(static fn (Timeframe $t): string => $t->value, Timeframe::cases());

        return [
            'symbol' => [
                'required',
                'string',
                Rule::exists('symbols', 'code')->where('is_active', true),
            ],
            'timeframe' => ['required', 'string', Rule::in($timeframes)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],

            'stoch_k' => ['nullable', 'integer', 'min:2', 'max:100'],
            'stoch_d' => ['nullable', 'integer', 'min:1', 'max:50'],
            'stoch_smooth' => ['nullable', 'integer', 'min:1', 'max:50'],

            'sr_lookback' => ['nullable', 'integer', 'min:50', 'max:2000'],
            'sr_max_levels' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sr_swing' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sr_cluster_pct' => ['nullable', 'numeric', 'min:0.0001', 'max:0.05'],
        ];
    }
}
