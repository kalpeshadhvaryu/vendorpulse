<?php

namespace App\EmailMonitoring\Enums;

enum InvoiceExtractionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
}
