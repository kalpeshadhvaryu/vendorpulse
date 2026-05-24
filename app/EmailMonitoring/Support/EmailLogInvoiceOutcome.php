<?php

namespace App\EmailMonitoring\Support;

/**
 * Human-readable invoice automation outcome from email_logs.processing_meta.
 */
final class EmailLogInvoiceOutcome
{
    /**
     * @return array{
     *     kind: string,
     *     label: string,
     *     reason: string|null,
     *     invoice_id: string|null,
     *     invoice_number: string|null,
     *     event: string|null
     * }
     */
    public static function fromProcessingMeta(?array $meta): array
    {
        if (! is_array($meta)) {
            return self::empty();
        }

        if (isset($meta['invoice_automation']) && is_array($meta['invoice_automation'])) {
            $automation = $meta['invoice_automation'];
            $action = (string) ($automation['action'] ?? 'processed');
            $number = isset($automation['invoice_number']) ? (string) $automation['invoice_number'] : null;
            $event = isset($automation['event']) ? (string) $automation['event'] : null;

            return [
                'kind' => $action === 'created' ? 'invoice_created' : 'invoice_updated',
                'label' => $action === 'created'
                    ? ($number ? "Created invoice {$number}" : 'Created invoice')
                    : ($number ? "Updated invoice {$number}" : 'Updated invoice'),
                'reason' => null,
                'invoice_id' => isset($automation['invoice_id']) ? (string) $automation['invoice_id'] : null,
                'invoice_number' => $number,
                'event' => $event,
            ];
        }

        if (array_key_exists('invoice_automation_skipped', $meta)) {
            $reason = (string) $meta['invoice_automation_skipped'];

            return [
                'kind' => 'skipped',
                'label' => self::skippedLabel($reason),
                'reason' => $reason,
                'invoice_id' => null,
                'invoice_number' => null,
                'event' => null,
            ];
        }

        if (($meta['invoice_extraction_skipped'] ?? null) === 'auto_detect_invoices_disabled') {
            return [
                'kind' => 'skipped',
                'label' => 'Invoice detection disabled on vendor',
                'reason' => 'auto_detect_invoices_disabled',
                'invoice_id' => null,
                'invoice_number' => null,
                'event' => null,
            ];
        }

        if (($meta['skip_vendor_email_automation'] ?? false) === true
            || ($meta['vendor_skip_reason'] ?? null) === 'auto_fetch_email_disabled') {
            return [
                'kind' => 'skipped',
                'label' => 'Vendor auto-fetch disabled',
                'reason' => (string) ($meta['vendor_skip_reason'] ?? 'auto_fetch_email_disabled'),
                'invoice_id' => null,
                'invoice_number' => null,
                'event' => null,
            ];
        }

        return self::empty();
    }

    /**
     * @return array{
     *     kind: string,
     *     label: string,
     *     reason: string|null,
     *     invoice_id: string|null,
     *     invoice_number: string|null,
     *     event: string|null
     * }
     */
    private static function empty(): array
    {
        return [
            'kind' => 'none',
            'label' => 'No invoice action',
            'reason' => null,
            'invoice_id' => null,
            'invoice_number' => null,
            'event' => null,
        ];
    }

    private static function skippedLabel(string $reason): string
    {
        return match ($reason) {
            'missing_invoice_number' => 'Skipped — no invoice number found',
            'low_confidence' => 'Skipped — low extraction confidence',
            'payment_without_existing_invoice' => 'Skipped — payment email, no matching invoice',
            default => 'Skipped — '.$reason,
        };
    }

    /**
     * @return array{email_log_id: string, subject: string|null, from_email: string|null, received_at: string|null}|null
     */
    public static function invoiceEmailSourceFromMetadata(?array $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $logId = $metadata['email_log_id'] ?? $metadata['last_auto_update_email_log_id'] ?? null;
        if (! is_string($logId) || $logId === '') {
            return null;
        }

        return [
            'email_log_id' => $logId,
            'auto_generated' => (bool) ($metadata['auto_generated'] ?? false),
            'source' => isset($metadata['source']) ? (string) $metadata['source'] : null,
        ];
    }
}
