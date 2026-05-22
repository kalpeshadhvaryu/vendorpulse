<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExperienceMonitoringScreenshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExperienceMonitoringScreenshot */
class ExperienceMonitoringScreenshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'experience_monitoring_run_id' => $this->experience_monitoring_run_id,
            'organization_id' => $this->organization_id,
            'path' => $this->path,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
