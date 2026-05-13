<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class QueuedInAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $eventKey,
        public array $payload = []
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->eventKey,
            'payload' => $this->payload,
        ];
    }
}
