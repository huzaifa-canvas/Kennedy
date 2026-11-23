<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nextDue = $this->next_due_date;
        $daysUntilDue = $nextDue ? Carbon::today()->diffInDays($nextDue, false) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date?->toDateString(),
            'next_due_date' => $nextDue?->toDateString(),
            'days_until_due' => $daysUntilDue !== null ? (int) $daysUntilDue : null,
            'due_label' => $this->dueLabel($daysUntilDue),
            'recurrence' => $this->recurrence,
            'color' => $this->color ?? $this->category?->color,
            'icon' => $this->category?->icon,
            'icon_url' => $this->category?->icon_url,
            'reminder_days_before' => (int) $this->reminder_days_before,
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'archived_by_plan' => $this->isArchivedByPlan(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'category' => new BillCategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function dueLabel(?int $days): ?string
    {
        return match (true) {
            $days === null => null,
            $days < 0 => 'Overdue',
            $days === 0 => 'Due Today',
            $days === 1 => 'Due Tomorrow',
            default => "Due In {$days} Days",
        };
    }
}
