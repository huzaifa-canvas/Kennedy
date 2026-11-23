<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'display_status' => $this->displayStatus(),
            'is_active' => $this->isActive(),
            'is_trial' => $this->isTrial(),
            'provider' => $this->provider,
            'store' => $this->store,
            'product_id' => $this->product_id,
            'will_renew' => (bool) $this->will_renew,
            'started_at' => $this->started_at?->toIso8601String(),
            'trial_started_at' => $this->trial_started_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'renews_at' => $this->renews_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'access_ends_at' => $this->accessEndsAt()?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'billing_issue' => $this->inBillingGrace(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
            'manage_subscription_url' => $this->managementUrl(),
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
