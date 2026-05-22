<?php

namespace App\ExperienceMonitoring\Services;

use App\ExperienceMonitoring\Enums\ExperienceMonitoringRunStatus;
use App\Models\ExperienceMonitoringRun;
use App\Models\ExperienceMonitoringTest;
use App\Models\Organization;
use App\Services\Notifications\NotificationDispatchService;

class ExperienceMonitoringAlertService
{
    public function __construct(
        protected NotificationDispatchService $notifications,
    ) {}

    public function dispatchAlerts(ExperienceMonitoringTest $test, ExperienceMonitoringRun $run): void
    {
        $organization = Organization::query()
            ->whereKey($test->organization_id)
            ->first();

        if (! $organization) {
            return;
        }

        $users = $organization->users()->get();
        if ($users->isEmpty()) {
            return;
        }

        $status = $run->status instanceof ExperienceMonitoringRunStatus
            ? $run->status
            : ExperienceMonitoringRunStatus::tryFrom((string) $run->status);

        if ($status === null || $status === ExperienceMonitoringRunStatus::Ok) {
            return;
        }

        $eventKey = match ($status) {
            ExperienceMonitoringRunStatus::FailedLogin => 'experience_monitoring.failed_login',
            ExperienceMonitoringRunStatus::SlowDashboard => 'experience_monitoring.slow_dashboard',
            ExperienceMonitoringRunStatus::Timeout => 'experience_monitoring.timeout',
            ExperienceMonitoringRunStatus::JsError => 'experience_monitoring.js_error',
            default => 'experience_monitoring.error',
        };

        $this->notifications->notify($users, $eventKey, [
            'test_id' => $test->id,
            'test_name' => $test->name,
            'status' => $status->value,
            'dashboard_url' => $test->dashboard_url,
            'run_id' => $run->id,
            'error_message' => $run->error_message,
            'dashboard_load_duration_ms' => $run->dashboard_load_duration_ms,
        ]);
    }
}
