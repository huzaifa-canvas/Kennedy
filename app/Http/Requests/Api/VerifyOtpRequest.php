<?php

namespace App\Http\Requests\Api;

class VerifyOtpRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'otp' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.digits' => 'The code is 6 digits.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
            'otp' => is_string($this->otp) ? trim($this->otp) : $this->otp,
        ]);
    }

    /** Throttle key is per credential pair, not just per IP. */
    public function throttleKey(): string
    {
        return 'otp-verify|' . strtolower((string) $this->input('email')) . '|' . $this->ip();
    }
}
