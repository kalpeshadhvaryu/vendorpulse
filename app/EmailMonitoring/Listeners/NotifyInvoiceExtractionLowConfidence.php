<?php

namespace App\EmailMonitoring\Listeners;

use App\EmailMonitoring\Events\InvoiceExtractionLowConfidence;
use App\Services\Notifications\NotificationDispatchService;

class NotifyInvoiceExtractionLowConfidence
{
    public function __construct(
        protected NotificationDispatchService $notifications,
    ) {}

    public function handle(InvoiceExtractionLowConfidence $event): void
    {
        $users = $event->extraction->organization->users;

        $this->notifications->notify(
            $users,
            'email_monitoring.invoice_extraction_low_confidence',
            [
                'email_log_id' => $event->extraction->email_log_id,
                'extraction_id' => $event->extraction->id,
                'aggregate_confidence' => $event->extraction->aggregate_confidence,
            ]
        );
    }
}
