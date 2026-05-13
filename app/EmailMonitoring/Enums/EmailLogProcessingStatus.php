<?php

namespace App\EmailMonitoring\Enums;

enum EmailLogProcessingStatus: string
{
    case Pending = 'pending';
    case Normalized = 'normalized';
    case Matched = 'matched';
    case Extracted = 'extracted';
    case Completed = 'completed';
    case Failed = 'failed';
}
