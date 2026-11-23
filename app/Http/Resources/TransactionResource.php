<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $amount = (float) $this->amount;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'amount' => $amount,
            'signed_amount' => $this->type === 'income' ? $amount : -$amount,
            'display_amount' => ($this->type === 'income' ? '+$' : '$') . number_format($amount, 2),
            'occurred_on' => $this->occurred_on->toDateString(),
            'icon' => $this->icon ?? $this->category?->icon,
            'icon_url' => $this->category?->icon_url,
            'color' => $this->category?->color,
            'category' => new BillCategoryResource($this->whenLoaded('category')),
        ];
    }
}
