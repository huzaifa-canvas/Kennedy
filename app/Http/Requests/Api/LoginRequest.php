<?php

namespace App\Http\Requests\Api;

class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
        ]);
    }

    /** Throttle key is per credential pair, not just per IP. */
    public function throttleKey(): string
    {
        return strtolower((string) $this->input('email')) . '|' . $this->ip();
    }
}
