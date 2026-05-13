<?php

namespace App\EmailMonitoring\Infrastructure;

use App\EmailMonitoring\Contracts\MailboxConnectorFactoryInterface;
use App\EmailMonitoring\Contracts\MailboxConnectorInterface;
use App\EmailMonitoring\Enums\MailboxDriver;
use App\EmailMonitoring\Infrastructure\Connectors\GmailApiMailboxConnector;
use App\EmailMonitoring\Infrastructure\Connectors\ImapMailboxConnector;
use App\Models\EmailMailbox;
use InvalidArgumentException;

class MailboxConnectorFactory implements MailboxConnectorFactoryInterface
{
    public function __construct(
        protected ImapMailboxConnector $imap,
        protected GmailApiMailboxConnector $gmail,
    ) {}

    public function forMailbox(EmailMailbox $mailbox): MailboxConnectorInterface
    {
        return match ($mailbox->driver) {
            MailboxDriver::Imap => $this->imap,
            MailboxDriver::GmailApi => $this->gmail,
            default => throw new InvalidArgumentException('Unsupported mailbox driver: '.$mailbox->driver->value),
        };
    }
}
