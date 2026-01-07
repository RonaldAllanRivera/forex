<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCandlesAllStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'symbol' => [
                'required',
                'string',
                Rule::exists('symbols', 'code')->where('is_active', true),
            ],
        ];
    }
}
