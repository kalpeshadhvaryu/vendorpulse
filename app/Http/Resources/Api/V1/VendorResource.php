<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vendor */
class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'vendor_type' => $this->vendor_type instanceof \BackedEnum ? $this->vendor_type->value : $this->vendor_type,
            'billing_email' => $this->billing_email,
            'support_email' => $this->support_email,
            'website' => $this->website,
            'currency' => $this->currency,
            'expected_amount' => $this->expected_amount !== null ? (string) $this->expected_amount : null,
            'billing_cycle' => $this->billing_cycle instanceof \BackedEnum ? $this->billing_cycle->value : $this->billing_cycle,
            'renewal_date' => $this->renewal_date?->format('Y-m-d'),
            'auto_detect_invoices' => (bool) $this->auto_detect_invoices,
            'auto_fetch_email' => (bool) $this->auto_fetch_email,
            'match_inbound_from_website_domain' => (bool) ($this->match_inbound_from_website_domain ?? false),
            'monitoring_enabled' => (bool) $this->monitoring_enabled,
            'notes' => $this->notes,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
