<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaycheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'set_aside_amount' => (float) $this->set_aside_amount,
            'received_on' => $this->received_on->toDateString(),
            'income_source' => new IncomeSourceResource($this->whenLoaded('incomeSource')),
        ];
    }
}
