<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\NormalizesTimezone;
use App\Support\Timezone;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends ApiRequest
{
    use NormalizesTimezone;

    public function rules(): array
    {
        return [
            'notifications_enabled' => ['sometimes', 'boolean'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'PKR', 'INR'])],
            'allowance_view' => ['sometimes', Rule::in(['weekly', 'daily'])],
            'timezone' => ['sometimes', 'string', 'max:64', Timezone::rule()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeTimezone();
    }
}
