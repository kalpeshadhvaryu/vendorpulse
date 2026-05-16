<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\QueuedInAppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotificationDispatchService
{
    /**
     * Queue in-app (database) notifications for the given users.
     *
     * @param  iterable<User>|User  $notifiables
     */
    public function notify(
        iterable|User $notifiables,
        string $eventKey,
        array $payload = []
    ): void {
        $collection = $notifiables instanceof User
            ? collect([$notifiables])
            : Collection::wrap($notifiables);

        try {
            Notification::send($collection, new QueuedInAppNotification($eventKey, $payload));
        } catch (Throwable $e) {
            // Core business actions should not fail if notification delivery/storage fails.
            Log::warning('Failed to dispatch in-app notification', [
                'event_key' => $eventKey,
                'notifiable_count' => $collection->count(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
