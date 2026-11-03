<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'price_label' => '$' . number_format((float) $this->price, 2) . '/' . $this->billing_period,
            'store_product_id' => $this->store_product_id,
            'is_free' => $this->isFree(),
            'limits' => [
                'bills' => $this->bill_limit,
                'income_sources' => $this->income_source_limit,
                'savings_jars' => $this->savings_jar_limit,
                'history_days' => $this->history_days,
            ],
            'capabilities' => [
                'smart_splits' => (bool) $this->allows_smart_splits,
                'custom_reminders' => (bool) $this->allows_custom_reminders,
                'advanced_recurrence' => (bool) $this->allows_advanced_recurrence,
                'calendar_themes' => (bool) $this->allows_calendar_themes,
            ],
            'features' => $this->features ?? [],
        ];
    }
}
