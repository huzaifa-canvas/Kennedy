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
            'is_active' => $this->isActive(),
            'provider' => $this->provider,
            'started_at' => $this->started_at?->toIso8601String(),
            'renews_at' => $this->renews_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
