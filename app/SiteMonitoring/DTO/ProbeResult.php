<?php

namespace App\SiteMonitoring\DTO;

use App\SiteMonitoring\Enums\MonitoringLogStatus;

final readonly class ProbeResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public MonitoringLogStatus $status,
        public ?string $message = null,
        public ?int $httpStatus = null,
        public ?int $responseTimeMs = null,
        public array $meta = [],
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(MonitoringLogStatus::Skipped, $reason);
    }

    public static function error(string $message): self
    {
        return new self(MonitoringLogStatus::Error, $message);
    }
}
