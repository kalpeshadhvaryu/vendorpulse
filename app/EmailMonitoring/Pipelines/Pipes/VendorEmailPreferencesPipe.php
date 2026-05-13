<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\Support\VendorEmailAutomation;
use App\Models\EmailLog;
use Closure;

class VendorEmailPreferencesPipe
{
    public function handle(EmailLog $log, Closure $next): mixed
    {
        $log = VendorEmailAutomation::applyAfterVendorMatch($log->fresh());

        return $next($log);
    }
}
