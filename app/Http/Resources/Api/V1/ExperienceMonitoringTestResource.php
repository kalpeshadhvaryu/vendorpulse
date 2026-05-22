<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExperienceMonitoringTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExperienceMonitoringTest */
class ExperienceMonitoringTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'login_url' => $this->login_url,
            'login_username' => $this->login_username,
            'dashboard_url' => $this->dashboard_url,
            'interval_seconds' => $this->interval_seconds,
            'browser_type' => $this->browser_type,
            'timeout_ms' => $this->timeout_ms,
            'concurrent_sessions' => $this->concurrent_sessions,
            'configuration' => $this->configuration,
            'thresholds' => $this->thresholds,
            'enabled' => $this->enabled,
            'last_status' => $this->last_status,
            'last_error' => $this->last_error,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
