<?php

namespace App\Http\Requests\Api;

class StoreSavingsJarRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'target_amount' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'saved_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'lte:target_amount'],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9\-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'saved_amount.lte' => 'The amount already saved cannot be more than your target.',
        ];
    }
}
