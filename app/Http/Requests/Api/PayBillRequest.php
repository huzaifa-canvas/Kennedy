<?php

namespace App\Http\Requests\Api;

class PayBillRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now', 'after_or_equal:2000-01-01'],
        ];
    }
}
