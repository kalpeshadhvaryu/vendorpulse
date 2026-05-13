<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\Events\InvoiceExtractionLowConfidence;
use App\EmailMonitoring\Events\InvoiceExtractionRecorded;
use App\EmailMonitoring\Events\VendorEmailMatchedForMonitoring;
use App\Models\EmailLog;
use Closure;
use Illuminate\Support\Facades\Event;

class DispatchEmailMonitoringEventsPipe
{
    public function handle(EmailLog $log, Closure $next): mixed
    {
        $fresh = $log->fresh(['invoiceExtractions']);

        if ($fresh->vendor_id) {
            Event::dispatch(new VendorEmailMatchedForMonitoring($fresh));
        }

        $extraction = $fresh->invoiceExtractions()->latest()->first();

        if ($extraction) {
            Event::dispatch(new InvoiceExtractionRecorded($extraction));

            $threshold = (float) config('email-monitoring.extraction.low_confidence_threshold', 0.65);

            if ($extraction->aggregate_confidence !== null && (float) $extraction->aggregate_confidence < $threshold) {
                Event::dispatch(new InvoiceExtractionLowConfidence($extraction));
            }
        }

        return $next($fresh);
    }
}
