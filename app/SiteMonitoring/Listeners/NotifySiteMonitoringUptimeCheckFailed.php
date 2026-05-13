<?php

namespace App\SiteMonitoring\Listeners;

use App\Services\Notifications\NotificationDispatchService;
use App\SiteMonitoring\Events\SiteMonitoringUptimeCheckFailed;

class NotifySiteMonitoringUptimeCheckFailed
{
    public function __construct(
        protected NotificationDispatchService $notifications,
    ) {}

    public function handle(SiteMonitoringUptimeCheckFailed $event): void
    {
        $users = $event->check->organization?->users;
        if (! $users || $users->isEmpty()) {
            return;
        }

        $this->notifications->notify(
            $users,
            'site_monitoring.uptime_check_failed',
            [
                'monitoring_check_id' => $event->check->id,
                'monitoring_log_id' => $event->log->id,
                'http_status' => $event->log->http_status,
                'response_time_ms' => $event->log->response_time_ms,
                'message' => $event->log->message,
            ]
        );
    }
}
