<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsJarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_amount' => (float) $this->target_amount,
            'saved_amount' => (float) $this->saved_amount,
            'remaining_amount' => round(max(0, (float) $this->target_amount - (float) $this->saved_amount), 2),
            'progress_percent' => $this->progress_percent,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_completed' => ! is_null($this->completed_at),
            'is_archived' => (bool) $this->is_archived,
            'archived_by_plan' => $this->isArchivedByPlan(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
