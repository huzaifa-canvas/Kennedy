<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StorePaycheckRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'received_on' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:2000-01-01'],
            'income_source_id' => [
                'nullable', 'integer',
                Rule::exists('income_sources', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
