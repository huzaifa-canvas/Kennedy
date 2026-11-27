<?php

namespace App\Http\Requests\Api\Concerns;

use App\Support\Timezone;

/**
 * Lets the app send the device timezone as either "Asia/Karachi" or an
 * offset such as "UTC+5"; offsets are converted to "+05:00" before validation.
 */
trait NormalizesTimezone
{
    protected function normalizeTimezone(): void
    {
        if (is_string($this->input('timezone'))) {
            $this->merge(['timezone' => Timezone::normalize($this->input('timezone'))]);
        }
    }
}
