<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MonitoringCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MonitoringCheck */
class MonitoringCheckResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type,
            'endpoint' => $this->endpoint,
            'configuration' => $this->configuration,
            'interval_seconds' => $this->interval_seconds,
            'enabled' => $this->enabled,
            'last_status' => $this->last_status,
            'last_message' => $this->last_message,
            'last_http_status' => $this->last_http_status,
            'last_response_time_ms' => $this->last_response_time_ms,
            'consecutive_failures' => $this->consecutive_failures,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
