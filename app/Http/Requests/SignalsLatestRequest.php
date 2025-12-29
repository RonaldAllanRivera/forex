<?php

namespace App\Http\Requests;

use App\Enums\Timeframe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignalsLatestRequest extends FormRequest
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
        ];
    }
}
