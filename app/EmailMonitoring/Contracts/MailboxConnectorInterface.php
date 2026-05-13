<?php

namespace App\EmailMonitoring\Contracts;

use App\EmailMonitoring\DTO\MailboxSyncCursor;
use App\EmailMonitoring\DTO\RawEmailEnvelope;
use App\Models\EmailMailbox;
use Generator;

/**
 * Transport-specific mailbox access (IMAP, Gmail API, Microsoft Graph, …).
 */
interface MailboxConnectorInterface
{
    public function driver(): string;

    /**
     * @return Generator<int, RawEmailEnvelope>
     */
    public function fetchSince(EmailMailbox $mailbox, ?MailboxSyncCursor $cursor = null): Generator;

    public function healthCheck(EmailMailbox $mailbox): bool;
}
