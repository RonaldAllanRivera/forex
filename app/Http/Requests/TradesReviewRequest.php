<?php

namespace App\Http\Requests;

use App\Enums\Timeframe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TradesReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()?->is_admin;
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
            'side' => ['required', 'string', Rule::in(['BUY', 'SELL'])],
            'entry_price' => ['required', 'numeric'],
            'stop_loss' => ['required', 'numeric'],
            'take_profit' => ['nullable', 'numeric'],
            'opened_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $this->validated();

            $side = $data['side'] ?? null;
            $entry = array_key_exists('entry_price', $data) ? (float) $data['entry_price'] : null;
            $stop = array_key_exists('stop_loss', $data) ? (float) $data['stop_loss'] : null;

            if (! is_string($side) || $entry === null || $stop === null) {
                return;
            }

            if ($side === 'BUY' && $stop >= $entry) {
                $validator->errors()->add('stop_loss', 'For BUY trades, stop_loss must be below entry_price.');
            }

            if ($side === 'SELL' && $stop <= $entry) {
                $validator->errors()->add('stop_loss', 'For SELL trades, stop_loss must be above entry_price.');
            }
        });
    }
}
