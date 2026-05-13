<?php

namespace App\EmailMonitoring\Contracts;

use App\Models\EmailMailbox;

interface MailboxConnectorFactoryInterface
{
    public function forMailbox(EmailMailbox $mailbox): MailboxConnectorInterface;
}
