<?php

namespace App\EmailMonitoring\Events;

use App\Models\EmailInvoiceExtraction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceExtractionRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public EmailInvoiceExtraction $extraction
    ) {}
}
