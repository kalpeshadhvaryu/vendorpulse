<?php

namespace App\Models;

use App\ExperienceMonitoring\Enums\ExperienceMonitoringRunStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExperienceMonitoringRun extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    protected $fillable = [
        'experience_monitoring_test_id',
        'organization_id',
        'session_index',
        'status',
        'login_duration_ms',
        'dashboard_load_duration_ms',
        'total_duration_ms',
        'http_status',
        'failed_requests_count',
        'js_errors_count',
        'screenshot_path',
        'http_status_codes',
        'response_times',
        'browser_logs',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExperienceMonitoringRunStatus::class,
            'session_index' => 'integer',
            'login_duration_ms' => 'integer',
            'dashboard_load_duration_ms' => 'integer',
            'total_duration_ms' => 'integer',
            'http_status' => 'integer',
            'failed_requests_count' => 'integer',
            'js_errors_count' => 'integer',
            'http_status_codes' => 'array',
            'response_times' => 'array',
            'browser_logs' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(ExperienceMonitoringTest::class, 'experience_monitoring_test_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ExperienceMonitoringRunMetric::class, 'experience_monitoring_run_id');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(ExperienceMonitoringExecutionLog::class, 'experience_monitoring_run_id');
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(ExperienceMonitoringScreenshot::class, 'experience_monitoring_run_id');
    }
}
