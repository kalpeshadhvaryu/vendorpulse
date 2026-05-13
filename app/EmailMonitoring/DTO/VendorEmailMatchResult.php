<?php

namespace App\EmailMonitoring\DTO;

final readonly class VendorEmailMatchResult
{
    public function __construct(
        public ?string $vendorId,
        public ?string $vendorEmailId,
        public float $confidence,
        public string $strategy,
        public array $evidence = [],
    ) {}

    public function matched(): bool
    {
        return $this->vendorId !== null && $this->confidence > 0;
    }
}
