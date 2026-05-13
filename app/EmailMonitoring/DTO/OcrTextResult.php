<?php

namespace App\EmailMonitoring\DTO;

final readonly class OcrTextResult
{
    public function __construct(
        public ?string $text,
        public float $confidence,
        public string $provider,
        public bool $skipped = false,
        public array $meta = [],
    ) {}
}
