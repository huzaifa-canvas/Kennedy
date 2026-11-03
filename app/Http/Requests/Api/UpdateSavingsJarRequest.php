<?php

namespace App\Http\Requests\Api;

class UpdateSavingsJarRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'target_amount' => ['sometimes', 'required', 'numeric', 'min:1', 'max:99999999.99'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[a-z0-9\-]+$/'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_archived' => ['sometimes', 'boolean'],
        ];
    }
}
