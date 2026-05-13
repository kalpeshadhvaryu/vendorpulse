<?php

namespace App\EmailMonitoring\Contracts;

use App\EmailMonitoring\DTO\InvoiceExtractionResult;
use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\Models\Vendor;

interface InvoiceCandidateExtractorInterface
{
    public function extract(NormalizedInboundEmail $email, ?Vendor $vendor, string $organizationId): InvoiceExtractionResult;
}
