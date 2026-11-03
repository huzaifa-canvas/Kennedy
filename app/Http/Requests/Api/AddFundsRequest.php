<?php

namespace App\Http\Requests\Api;

class AddFundsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:2000-01-01'],
        ];
    }
}
