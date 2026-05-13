<?php

namespace App\EmailMonitoring\Infrastructure\Connectors;

use App\EmailMonitoring\Contracts\MailboxConnectorInterface;
use App\EmailMonitoring\DTO\MailboxSyncCursor;
use App\EmailMonitoring\Enums\MailboxDriver;
use App\Models\EmailMailbox;
use Generator;

/**
 * Gmail API connector — wire to google/apiclient services/gmail in a future PR.
 */
class GmailApiMailboxConnector implements MailboxConnectorInterface
{
    public function driver(): string
    {
        return 'gmail_api';
    }

    public function fetchSince(EmailMailbox $mailbox, ?MailboxSyncCursor $cursor = null): Generator
    {
        if ($mailbox->driver !== MailboxDriver::GmailApi) {
            yield from [];

            return;
        }

        yield from [];
    }

    public function healthCheck(EmailMailbox $mailbox): bool
    {
        return $mailbox->driver === MailboxDriver::GmailApi;
    }
}
