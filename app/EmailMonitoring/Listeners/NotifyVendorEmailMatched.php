<?php

namespace App\EmailMonitoring\Listeners;

use App\EmailMonitoring\Events\VendorEmailMatchedForMonitoring;
use App\Services\Notifications\NotificationDispatchService;

class NotifyVendorEmailMatched
{
    public function __construct(
        protected NotificationDispatchService $notifications,
    ) {}

    public function handle(VendorEmailMatchedForMonitoring $event): void
    {
        $users = $event->emailLog->organization->users;

        $this->notifications->notify(
            $users,
            'email_monitoring.vendor_email_matched',
            [
                'email_log_id' => $event->emailLog->id,
                'vendor_id' => $event->emailLog->vendor_id,
                'vendor_email_id' => $event->emailLog->vendor_email_id,
                'confidence' => $event->emailLog->vendor_match_confidence,
            ]
        );
    }
}
