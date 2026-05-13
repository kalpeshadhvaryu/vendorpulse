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
        $due = $this->guessDate($text, ['due', 'payable']);
        $invoiceNo = $this->guessInvoiceNumber($text);

        $payload = [
            'organization_id' => $organizationId,
            'vendor_id' => $vendor?->id,
            'external_message_id' => $email->externalMessageId,
            'candidate_invoice_number' => $invoiceNo,
            'candidate_amount' => $amount,
            'candidate_due_date' => $due,
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

    private function guessDate(string $text, array $keywords): ?string
    {
        $escaped = array_map(static fn (string $k): string => preg_quote($k, '/'), $keywords);
        $pattern = '/(?:'.implode('|', $escaped).')[^\d]{0,20}(\d{4}-\d{2}-\d{2})/i';

        if (preg_match($pattern, $text, $m)) {
            return $m[1];
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
}
