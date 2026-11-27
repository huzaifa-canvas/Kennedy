<?php

namespace App\Http\Requests\Api;

use App\Models\IncomeSource;
use Illuminate\Validation\Rule;

class UpdateIncomeSourceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999.99'],
            'pay_frequency' => ['sometimes', 'required', Rule::in(IncomeSource::FREQUENCIES)],
            'next_payday' => ['sometimes', 'nullable', 'date', 'after_or_equal:2000-01-01', 'before:+5 years'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
