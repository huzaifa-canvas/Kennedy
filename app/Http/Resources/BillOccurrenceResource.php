<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class BillOccurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $days = (int) Carbon::today()->diffInDays($this->due_date, false);

        return [
            'id' => $this->id,
            'bill_id' => $this->bill_id,
            'name' => $this->bill?->name,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date->toDateString(),
            'due_label' => match (true) {
                $this->status === 'paid' => 'Paid',
                $days < 0 => 'Overdue',
                $days === 0 => 'Due Today',
                $days === 1 => 'Due Tomorrow',
                default => "Due In {$days} Days",
            },
            'days_until_due' => $days,
            'status' => $this->status,
            'paid_amount' => $this->paid_amount !== null ? (float) $this->paid_amount : null,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'color' => $this->bill?->color ?? $this->bill?->category?->color,
            'icon' => $this->bill?->category?->icon,
            'category' => $this->whenLoaded('bill', fn () => new BillCategoryResource($this->bill->category)),
        ];
    }
}
