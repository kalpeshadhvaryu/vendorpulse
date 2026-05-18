<?php

namespace App\EmailMonitoring\Infrastructure\Extraction;

use App\EmailMonitoring\Contracts\InvoiceCandidateExtractorInterface;
use App\EmailMonitoring\DTO\InvoiceExtractionResult;
use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\Models\Vendor;

/**
 * Lightweight heuristic extractor — replace with ML / template parsers behind the same interface.
 */
class HeuristicInvoiceCandidateExtractor implements InvoiceCandidateExtractorInterface
{
    public function extract(NormalizedInboundEmail $email, ?Vendor $vendor, string $organizationId): InvoiceExtractionResult
    {
        $text = $email->bodyText ?? strip_tags((string) ($email->bodyHtml ?? ''));
        $amount = $this->guessAmount($text);
        $currency = $this->guessCurrency($text);
        $due = $this->guessDate($text, ['due', 'payable']);
        $paidDate = $this->guessDate($text, ['paid', 'payment received', 'receipt']);
        $invoiceNo = $this->guessInvoiceNumber($text);
        $event = $this->detectEvent($email->subject, $text);

        $payload = [
            'organization_id' => $organizationId,
            'vendor_id' => $vendor?->id,
            'external_message_id' => $email->externalMessageId,
            'candidate_event' => $event,
            'candidate_invoice_number' => $invoiceNo,
            'candidate_amount' => $amount,
            'candidate_currency' => $currency,
            'candidate_due_date' => $due,
            'candidate_paid_at' => $paidDate,
            'currency_hint' => $vendor?->currency,
        ];

        $fieldScores = [
            'candidate_amount' => $amount !== null ? 0.45 : 0.0,
            'candidate_due_date' => $due !== null ? 0.4 : 0.0,
            'candidate_invoice_number' => $invoiceNo !== null ? 0.35 : 0.0,
        ];

        $aggregate = $fieldScores === []
            ? 0.0
            : round(array_sum($fieldScores) / max(1, count(array_filter($fieldScores))), 4);

        return new InvoiceExtractionResult(
            payload: array_filter($payload, static fn ($v) => $v !== null && $v !== ''),
            fieldConfidenceScores: $fieldScores,
            aggregateConfidence: min(1.0, $aggregate),
            driver: 'heuristic_v1',
            notes: ['Heuristic pass only — attach dedicated parsers as needed.'],
        );
    }

    private function guessAmount(string $text): ?float
    {
        if (preg_match('/(?:USD|EUR|GBP|\$|€|£)\s*([\d,]+\.\d{2})/i', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        if (preg_match('/([\d,]+\.\d{2})\s*(?:USD|EUR|GBP)/i', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        return null;
    }

    private function guessCurrency(string $text): ?string
    {
        if (preg_match('/\b(USD|EUR|GBP|AED|INR|CAD|AUD|JPY|SGD)\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }

        if (str_contains($text, '$')) {
            return 'USD';
        }

        if (str_contains($text, '€')) {
            return 'EUR';
        }

        if (str_contains($text, '£')) {
            return 'GBP';
        }

        return null;
    }

    private function guessDate(string $text, array $keywords): ?string
    {
        $escaped = array_map(static fn (string $k): string => preg_quote($k, '/'), $keywords);
        $pattern = '/(?:'.implode('|', $escaped).')[^\d]{0,20}(\d{4}-\d{2}-\d{2})/i';

        if (preg_match($pattern, $text, $m)) {
            return $m[1];
        }

        $altPattern = '/(?:'.implode('|', $escaped).')[^\d]{0,20}(\d{2}[\/\-]\d{2}[\/\-]\d{4})/i';

        if (preg_match($altPattern, $text, $m)) {
            $ts = strtotime($m[1]);

            return $ts === false ? null : date('Y-m-d', $ts);
        }

        return null;
    }

    private function guessInvoiceNumber(string $text): ?string
    {
        if (preg_match('/\b(INV[- ]?[A-Z0-9]{4,})\b/i', $text, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function detectEvent(?string $subject, string $text): string
    {
        $haystack = mb_strtolower(trim((string) $subject.' '.$text));

        $paymentSignal = preg_match('/payment\s+(received|successful|succeeded|confirmation)|invoice\s+paid|receipt\b|paid\s+in\s+full/i', $haystack) === 1;
        $dueSignal = preg_match('/payment\s+due|amount\s+due|past\s+due|due\s+date/i', $haystack) === 1;

        if ($paymentSignal && ! $dueSignal) {
            return 'payment_received';
        }

        return 'invoice_created';
    }
}
