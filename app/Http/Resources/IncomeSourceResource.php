<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'pay_frequency' => $this->pay_frequency,
            'pay_frequency_label' => match ($this->pay_frequency) {
                'weekly' => 'Weekly',
                'biweekly' => 'Every 2 Weeks',
                'semimonthly' => 'Twice A Month',
                'monthly' => 'Monthly',
                default => 'It Varies',
            },
            'next_payday' => $this->next_payday?->toDateString(),
            'monthly_equivalent' => $this->monthlyEquivalent(),
            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
            'archived_by_plan' => $this->isArchivedByPlan(),
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
