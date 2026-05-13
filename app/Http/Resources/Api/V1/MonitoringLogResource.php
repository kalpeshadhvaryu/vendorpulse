<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MonitoringLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MonitoringLog */
class MonitoringLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'id' => $this->id,
            'monitoring_check_id' => $this->monitoring_check_id,
            'organization_id' => $this->organization_id,
            'status' => $status instanceof \BackedEnum ? $status->value : $status,
            'http_status' => $this->http_status,
            'response_time_ms' => $this->response_time_ms,
            'message' => $this->message,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
