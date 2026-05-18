<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\Models\EmailInvoiceExtraction;
use App\Models\EmailLog;
use App\Models\Invoice;
use Closure;

class ApplyInvoiceAutomationPipe
{
    public function handle(EmailLog $log, Closure $next): mixed
    {
        if (! (bool) config('email-monitoring.invoice_automation.enabled', false)) {
            return $next($log);
        }

        $extraction = EmailInvoiceExtraction::query()
            ->where('email_log_id', $log->id)
            ->latest('created_at')
            ->first();

        if (! $extraction) {
            return $next($log);
        }

        $payload = $extraction->extracted_payload ?? [];
        $invoiceNumber = trim((string) ($payload['candidate_invoice_number'] ?? ''));

        if ($invoiceNumber === '') {
            $this->annotate($log, 'invoice_automation_skipped', 'missing_invoice_number');

            return $next($log);
        }

        $aggregateConfidence = (float) ($extraction->aggregate_confidence ?? 0);
        $minConfidence = (float) config('email-monitoring.invoice_automation.min_confidence', 0.80);

        if ($aggregateConfidence < $minConfidence) {
            $this->annotate($log, 'invoice_automation_skipped', 'low_confidence');

            return $next($log);
        }

        $event = (string) ($payload['candidate_event'] ?? 'invoice_created');

        $invoice = Invoice::query()
            ->where('organization_id', $log->organization_id)
            ->where('number', $invoiceNumber)
            ->first();

        if (! $invoice && $event === 'payment_received' && ! (bool) config('email-monitoring.invoice_automation.allow_create_on_paid', false)) {
            $this->annotate($log, 'invoice_automation_skipped', 'payment_without_existing_invoice');

            return $next($log);
        }

        $currency = strtoupper((string) ($payload['candidate_currency'] ?? ''));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = strtoupper((string) ($log->vendor?->currency ?? 'USD'));
        }
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'USD';
        }

        $amountCents = null;
        $candidateAmount = $payload['candidate_amount'] ?? null;
        if (is_numeric($candidateAmount)) {
            $amountCents = max(0, (int) round(((float) $candidateAmount) * 100));
        }

        $dueOn = $this->normalizeDateString($payload['candidate_due_date'] ?? null);
        $paidAt = $this->normalizeDateTimeString($payload['candidate_paid_at'] ?? null)
            ?? $log->received_at?->toIso8601String()
            ?? now()->toIso8601String();

        if (! $invoice) {
            $invoice = Invoice::query()->create([
                'organization_id' => $log->organization_id,
                'vendor_id' => $log->vendor_id,
                'number' => $invoiceNumber,
                'status' => $event === 'payment_received' ? 'paid' : 'open',
                'currency' => $currency,
                'amount_cents' => $amountCents ?? 0,
                'tax_cents' => 0,
                'issued_on' => $log->received_at?->toDateString() ?? now()->toDateString(),
                'due_on' => $dueOn,
                'paid_at' => $event === 'payment_received' ? $paidAt : null,
                'description' => 'Auto-created from inbound email processing.',
                'metadata' => [
                    'auto_generated' => true,
                    'source' => 'email_monitoring',
                    'email_log_id' => (string) $log->id,
                    'pipeline_driver' => (string) $extraction->pipeline_driver,
                ],
            ]);

            $this->annotate($log, 'invoice_automation', [
                'action' => 'created',
                'invoice_id' => (string) $invoice->id,
                'invoice_number' => $invoiceNumber,
                'event' => $event,
            ]);

            return $next($log);
        }

        $updates = [];

        if (! $invoice->vendor_id && $log->vendor_id) {
            $updates['vendor_id'] = $log->vendor_id;
        }

        if (($invoice->amount_cents ?? 0) === 0 && $amountCents !== null) {
            $updates['amount_cents'] = $amountCents;
        }

        if (! $invoice->currency && $currency) {
            $updates['currency'] = $currency;
        }

        if (! $invoice->due_on && $dueOn) {
            $updates['due_on'] = $dueOn;
        }

        if ($event === 'payment_received') {
            $updates['status'] = 'paid';
            if (! $invoice->paid_at) {
                $updates['paid_at'] = $paidAt;
            }
        }

        if ($updates !== []) {
            $metadata = is_array($invoice->metadata) ? $invoice->metadata : [];
            $metadata['last_auto_update_email_log_id'] = (string) $log->id;
            $metadata['last_auto_update_driver'] = (string) $extraction->pipeline_driver;
            $updates['metadata'] = $metadata;

            $invoice->update($updates);
        }

        $this->annotate($log, 'invoice_automation', [
            'action' => 'updated',
            'invoice_id' => (string) $invoice->id,
            'invoice_number' => $invoiceNumber,
            'event' => $event,
            'applied_updates' => array_keys($updates),
        ]);

        return $next($log);
    }

    private function annotate(EmailLog $log, string $key, mixed $value): void
    {
        $meta = $log->processing_meta ?? [];
        $meta[$key] = $value;

        $log->update([
            'processing_meta' => $meta,
        ]);
    }

    private function normalizeDateString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function normalizeDateTimeString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date(DATE_ATOM, $ts);
    }
}
