<?php

namespace App\EmailMonitoring\Support;

use App\Models\EmailLog;
use App\Models\Vendor;

final class VendorEmailAutomation
{
    public const META_SKIP_DOWNSTREAM = 'skip_vendor_email_automation';

    public static function shouldSkipDownstream(EmailLog $log): bool
    {
        return ($log->processing_meta[self::META_SKIP_DOWNSTREAM] ?? false) === true;
    }

    /**
     * When a vendor is matched and has opted out of inbound automation, skip attachment/OCR/invoice extraction.
     */
    public static function applyAfterVendorMatch(EmailLog $log): EmailLog
    {
        if (! $log->vendor_id) {
            return $log;
        }

        $vendor = Vendor::query()->find($log->vendor_id);

        if ($vendor === null || $vendor->auto_fetch_email) {
            return $log;
        }

        $log->update([
            'processing_meta' => array_merge($log->processing_meta ?? [], [
                self::META_SKIP_DOWNSTREAM => true,
                'vendor_skip_reason' => 'auto_fetch_email_disabled',
            ]),
        ]);

        return $log->fresh();
    }

    public static function shouldExtractInvoices(?Vendor $vendor): bool
    {
        return $vendor === null || $vendor->auto_detect_invoices;
    }
}
