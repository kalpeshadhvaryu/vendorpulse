<?php

namespace App\SiteMonitoring\Listeners;

use App\Services\Notifications\NotificationDispatchService;
use App\SiteMonitoring\Events\SiteMonitoringSslExpiringSoon;

class NotifySiteMonitoringSslExpiringSoon
{
    public function __construct(
        protected NotificationDispatchService $notifications,
    ) {}

    public function handle(SiteMonitoringSslExpiringSoon $event): void
    {
        $users = $event->check->organization?->users;
        if (! $users || $users->isEmpty()) {
            return;
        }

        $this->notifications->notify(
            $users,
            'site_monitoring.ssl_expiring_soon',
            [
                'monitoring_check_id' => $event->check->id,
                'monitoring_log_id' => $event->log->id,
                'host' => $event->log->meta['host'] ?? null,
                'ssl_days_remaining' => $event->log->meta['ssl_days_remaining'] ?? null,
            ]
        );
    }
}
