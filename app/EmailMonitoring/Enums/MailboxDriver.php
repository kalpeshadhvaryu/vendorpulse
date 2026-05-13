<?php

namespace App\EmailMonitoring\Enums;

enum MailboxDriver: string
{
    case Imap = 'imap';
    case GmailApi = 'gmail_api';
}
