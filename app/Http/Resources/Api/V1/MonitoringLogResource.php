<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MonitoringLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MonitoringLog */
class MonitoringLogResource extends JsonResource
{
    /**
     * Heavy HTTP probe fields kept for debugging in storage, but omitted from list/history payloads.
     *
     * @var list<string>
     */
    private const META_OMIT_KEYS = [
        'http_response_body_preview',
        'http_response_headers',
        'http_response_body_truncated',
        'http_response_size_bytes',
    ];

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
            'meta' => $this->slimMeta($this->meta),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null
     */
    private function slimMeta(mixed $meta): mixed
    {
        if (! is_array($meta)) {
            return $meta;
        }

        foreach (self::META_OMIT_KEYS as $key) {
            unset($meta[$key]);
        }

        return $meta === [] ? null : $meta;
    }
}
