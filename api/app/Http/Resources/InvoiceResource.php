<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'status' => $this->status,
            'overdue' => $this->isOverdue(),
            'due_on' => $this->due_on->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
