<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\Contracts\InvoiceCandidateExtractorInterface;
use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\EmailMonitoring\Enums\InvoiceExtractionStatus;
use App\EmailMonitoring\Support\VendorEmailAutomation;
use App\Models\EmailInvoiceExtraction;
use App\Models\EmailLog;
use App\Models\Vendor;
use Closure;

class ExtractInvoiceCandidatesPipe
{
    public function __construct(
        protected InvoiceCandidateExtractorInterface $extractor,
    ) {}

    public function handle(EmailLog $log, Closure $next): mixed
    {
        if (VendorEmailAutomation::shouldSkipDownstream($log)) {
            return $next($log);
        }

        $payload = $log->normalized_payload ?? [];

        if ($payload === []) {
            return $next($log);
        }

        $normalized = NormalizedInboundEmail::fromStoredArray($payload);
        $vendor = $log->vendor_id ? Vendor::query()->find($log->vendor_id) : null;

        if (! VendorEmailAutomation::shouldExtractInvoices($vendor)) {
            $log->update([
                'processing_meta' => array_merge($log->processing_meta ?? [], [
                    'invoice_extraction_skipped' => 'auto_detect_invoices_disabled',
                ]),
            ]);

            return $next($log->fresh());
        }

        $result = $this->extractor->extract(
            $normalized,
            $vendor,
            (string) $log->organization_id,
        );

        EmailInvoiceExtraction::query()->updateOrCreate(
            [
                'email_log_id' => $log->id,
                'pipeline_driver' => $result->driver,
            ],
            [
                'organization_id' => $log->organization_id,
                'vendor_id' => $vendor?->id,
                'status' => InvoiceExtractionStatus::Completed,
                'extracted_payload' => $result->payload,
                'field_confidence_scores' => $result->fieldConfidenceScores,
                'aggregate_confidence' => $result->aggregateConfidence,
                'processing_notes' => $result->notes,
            ]
        );

        $log->update([
            'processing_status' => EmailLogProcessingStatus::Extracted,
            'processing_meta' => array_merge($log->processing_meta ?? [], [
                'invoice_extraction_driver' => $result->driver,
            ]),
        ]);

        return $next($log->fresh());
    }
}
