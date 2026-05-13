<?php

namespace App\SiteMonitoring\Listeners;

use App\Services\Notifications\NotificationDispatchService;
use App\SiteMonitoring\Events\SiteMonitoringDomainExpiringSoon;

class NotifySiteMonitoringDomainExpiringSoon
{
    public function __construct(
        protected NotificationDispatchService $notifications,
    ) {}

    public function handle(SiteMonitoringDomainExpiringSoon $event): void
    {
        $users = $event->check->organization?->users;
        if (! $users || $users->isEmpty()) {
            return;
        }

        $this->notifications->notify(
            $users,
            'site_monitoring.domain_expiring_soon',
            [
                'monitoring_check_id' => $event->check->id,
                'monitoring_log_id' => $event->log->id,
                'domain' => $event->log->meta['domain'] ?? null,
                'domain_days_remaining' => $event->log->meta['domain_days_remaining'] ?? null,
            ]
        );
    }
}
