<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

/**
 * Posted by the app after RevenueCat reports a purchase or restore.
 * The server still re-checks the entitlement before trusting it.
 */
class SyncSubscriptionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'plan_slug' => ['required', 'string', Rule::exists('plans', 'slug')->where('is_active', true)],
            'provider_subscription_id' => ['required', 'string', 'max:191', 'regex:/^[A-Za-z0-9_\.\:\-]+$/'],
            'expires_at' => ['nullable', 'date', 'after:now', 'before:+10 years'],
            'is_trial' => ['nullable', 'boolean'],
        ];
    }
}
