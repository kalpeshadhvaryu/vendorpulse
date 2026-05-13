<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\QueuedInAppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

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

        Notification::send($collection, new QueuedInAppNotification($eventKey, $payload));
    }
}
