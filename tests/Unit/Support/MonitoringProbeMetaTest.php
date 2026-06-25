<?php

namespace Tests\Unit\Support;

use App\Support\MonitoringProbeMeta;
use PHPUnit\Framework\TestCase;

class MonitoringProbeMetaTest extends TestCase
{
    public function test_extracts_domain_and_ssl_fields_from_meta(): void
    {
        $extracted = MonitoringProbeMeta::extract([
            'domain' => 'example.com',
            'domain_expires_at' => '2026-12-01T00:00:00+00:00',
            'domain_days_remaining' => 12,
            'registrar' => 'Example Registrar',
            'certificate_expires_at' => '2026-08-01T00:00:00+00:00',
            'certificate_days_remaining' => 45,
        ]);

        $this->assertSame('example.com', $extracted['domain']);
        $this->assertSame('2026-12-01T00:00:00+00:00', $extracted['domain_expires_at']);
        $this->assertSame(12, $extracted['domain_days_remaining']);
        $this->assertSame('Example Registrar', $extracted['domain_registrar']);
        $this->assertSame('2026-08-01T00:00:00+00:00', $extracted['ssl_expires_at']);
        $this->assertSame(45, $extracted['ssl_days_remaining']);
    }

    public function test_returns_nulls_for_empty_meta(): void
    {
        $extracted = MonitoringProbeMeta::extract(null);

        $this->assertNull($extracted['domain']);
        $this->assertNull($extracted['domain_days_remaining']);
        $this->assertNull($extracted['ssl_days_remaining']);
    }
}
