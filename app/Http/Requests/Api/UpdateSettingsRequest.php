<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'notifications_enabled' => ['sometimes', 'boolean'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'PKR', 'INR'])],
            'allowance_view' => ['sometimes', Rule::in(['weekly', 'daily'])],
            'timezone' => ['sometimes', 'string', 'max:64', 'timezone'],
        ];
    }
}
