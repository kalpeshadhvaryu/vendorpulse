<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VendorEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VendorEmail */
class VendorEmailResource extends JsonResource
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
            'email' => $this->email,
            'label' => $this->label,
            'purpose' => $this->purpose,
            'is_monitored' => $this->is_monitored,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_check_status' => $this->last_check_status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
