<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\pM\s\.\-\x27]+$/u'],
            'email' => [
                'sometimes', 'required', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[0-9\+\-\s\(\)]+$/'],
            'pay_frequency' => ['sometimes', 'required', Rule::in(User::PAY_FREQUENCIES)],
            'next_payday' => ['sometimes', 'nullable', 'date', 'after_or_equal:1970-01-01', 'before:+5 years'],
            'estimated_paycheck_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64', 'timezone'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }
}
