<?php

namespace App\Http\Resources;

use App\Services\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->resource->plan();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'pay_frequency' => $this->pay_frequency,
            'next_payday' => $this->next_payday?->toDateString(),
            'estimated_paycheck_amount' => $this->estimated_paycheck_amount
                ? (float) $this->estimated_paycheck_amount
                : null,
            'allowance_view' => $this->allowance_view,
            'notifications_enabled' => (bool) $this->notifications_enabled,
            'onboarding_completed' => ! is_null($this->onboarding_completed_at),
            'member_since' => $this->created_at?->toDateString(),
            'plan' => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'is_pro' => ! $plan->isFree(),
                'bill_limit' => $plan->bill_limit,
                'savings_jar_limit' => $plan->savings_jar_limit,
                'income_source_limit' => $plan->income_source_limit,
                'allows_smart_splits' => (bool) $plan->allows_smart_splits,
            ],
            'subscription' => app(SubscriptionManager::class)->summary($this->resource),
        ];
    }
}
