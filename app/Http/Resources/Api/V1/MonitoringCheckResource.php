<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MonitoringCheck;
use App\Support\MonitoringProbeMeta;
use Carbon\Carbon;
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
        $probe = MonitoringProbeMeta::extract(is_array($this->last_meta) ? $this->last_meta : null);

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
            'domain' => $probe['domain'],
            'domain_expires_at' => $probe['domain_expires_at'],
            'domain_days_remaining' => $probe['domain_days_remaining'],
            'domain_registrar' => $probe['domain_registrar'],
            'ssl_expires_at' => $probe['ssl_expires_at'],
            'ssl_days_remaining' => $probe['ssl_days_remaining'],
            'consecutive_failures' => $this->consecutive_failures,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'uptime_since' => $this->resolveUptimeSince(),
        ];
    }

    private function resolveUptimeSince(): ?string
    {
        if ($this->last_status !== 'ok') {
            return null;
        }

        // Use eager-loaded aggregate when available (set by paginate via withMax).
        $attrs = $this->resource->getAttributes();
        if (array_key_exists('monitoring_logs_max_created_at', $attrs)) {
            $lastBadAt = $attrs['monitoring_logs_max_created_at'];
        } else {
            // Fallback for show() endpoint where the aggregate is not pre-loaded.
            $lastBadAt = $this->resource->monitoringLogs()
                ->whereIn('status', ['failed', 'error', 'degraded'])
                ->max('created_at');
        }

        return $lastBadAt
            ? Carbon::parse((string) $lastBadAt)->toIso8601String()
            : $this->created_at?->toIso8601String();
    }
}
