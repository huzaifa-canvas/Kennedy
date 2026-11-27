<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\NormalizesTimezone;
use App\Support\Timezone;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiRequest
{
    use NormalizesTimezone;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\pM\s\.\-\x27]+$/u'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9\+\-\s\(\)]+$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'timezone' => ['nullable', 'string', 'max:64', Timezone::rule()],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Your name may only contain letters, spaces, apostrophes, hyphens and dots.',
            'phone.regex' => 'Please enter a valid phone number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);

        $this->normalizeTimezone();
    }
}
