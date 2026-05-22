<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExperienceMonitoringRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExperienceMonitoringRun */
class ExperienceMonitoringRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'experience_monitoring_test_id' => $this->experience_monitoring_test_id,
            'organization_id' => $this->organization_id,
            'session_index' => $this->session_index,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'login_duration_ms' => $this->login_duration_ms,
            'dashboard_load_duration_ms' => $this->dashboard_load_duration_ms,
            'total_duration_ms' => $this->total_duration_ms,
            'http_status' => $this->http_status,
            'failed_requests_count' => $this->failed_requests_count,
            'js_errors_count' => $this->js_errors_count,
            'screenshot_path' => $this->screenshot_path,
            'http_status_codes' => $this->http_status_codes,
            'response_times' => $this->response_times,
            'browser_logs' => $this->browser_logs,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
