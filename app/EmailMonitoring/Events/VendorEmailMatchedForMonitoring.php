<?php

namespace App\EmailMonitoring\Events;

use App\Models\EmailLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorEmailMatchedForMonitoring
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public EmailLog $emailLog
    ) {}
}
