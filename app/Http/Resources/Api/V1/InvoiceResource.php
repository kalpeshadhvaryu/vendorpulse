<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'vendor_id' => $this->vendor_id,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,
            'amount_cents' => $this->amount_cents,
            'tax_cents' => $this->tax_cents,
            'issued_on' => $this->issued_on?->format('Y-m-d'),
            'due_on' => $this->due_on?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'description' => $this->description,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
