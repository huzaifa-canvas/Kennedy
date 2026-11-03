<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'savings_jar_id' => $this->savings_jar_id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'note' => $this->note,
            'occurred_on' => $this->occurred_on->toDateString(),
        ];
    }
}
