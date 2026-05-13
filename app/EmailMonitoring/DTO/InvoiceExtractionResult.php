<?php

namespace App\EmailMonitoring\DTO;

/**
 * @param  array<string, mixed>  $payload
 * @param  array<string, float>  $fieldConfidenceScores
 */
final readonly class InvoiceExtractionResult
{
    public function __construct(
        public array $payload,
        public array $fieldConfidenceScores,
        public float $aggregateConfidence,
        public string $driver,
        public array $notes = [],
    ) {}
}
