<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'push_token' => ['required', 'string', 'min:10', 'max:512'],
            'platform' => ['required', Rule::in(['ios', 'android', 'web'])],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:32', 'regex:/^[0-9A-Za-z\.\-\+]+$/'],
        ];
    }
}
