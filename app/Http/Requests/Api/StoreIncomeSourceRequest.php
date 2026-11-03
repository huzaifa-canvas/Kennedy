<?php

namespace App\Http\Requests\Api;

use App\Models\IncomeSource;
use Illuminate\Validation\Rule;

class StoreIncomeSourceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'pay_frequency' => ['required', Rule::in(IncomeSource::FREQUENCIES)],
            'next_payday' => ['required', 'date', 'after_or_equal:2000-01-01', 'before:+5 years'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
