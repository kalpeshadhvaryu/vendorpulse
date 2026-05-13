<?php

namespace App\EmailMonitoring\Infrastructure\Connectors;

use App\EmailMonitoring\Contracts\MailboxConnectorInterface;
use App\EmailMonitoring\DTO\MailboxSyncCursor;
use App\EmailMonitoring\Enums\MailboxDriver;
use App\Models\EmailMailbox;
use Generator;

/**
 * IMAP connector — wire to webklex/laravel-imap or native ext-imap in a future PR.
 */
class ImapMailboxConnector implements MailboxConnectorInterface
{
    public function driver(): string
    {
        return 'imap';
    }

    public function fetchSince(EmailMailbox $mailbox, ?MailboxSyncCursor $cursor = null): Generator
    {
        if ($mailbox->driver !== MailboxDriver::Imap) {
            yield from [];

            return;
        }

        yield from [];
    }

    public function healthCheck(EmailMailbox $mailbox): bool
    {
        return $mailbox->driver === MailboxDriver::Imap;
    }
}
