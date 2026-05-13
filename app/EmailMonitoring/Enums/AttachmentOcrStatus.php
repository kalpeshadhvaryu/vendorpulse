<?php

namespace App\EmailMonitoring\Enums;

enum AttachmentOcrStatus: string
{
    case Pending = 'pending';
    case Skipped = 'skipped';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
