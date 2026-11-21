<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportPdfApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_user_can_download_port_checker_pdf(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Organization-Id', $org->id)
            ->post('/api/v1/reports/pdf/port-checker', [
                'report' => [
                    'target' => 'example.com',
                    'host' => 'example.com',
                    'mode' => 'quick',
                    'scanned_at' => now()->toIso8601String(),
                    'summary' => [
                        'total_ports' => 1,
                        'open_ports' => 1,
                        'closed_ports' => 0,
                        'filtered_ports' => 0,
                        'risky_open_ports' => 0,
                    ],
                    'ports' => [[
                        'port' => 443,
                        'state' => 'open',
                        'latency_ms' => 10,
                        'service_hint' => 'https',
                        'risk' => null,
                        'banner' => null,
                        'error' => null,
                    ]],
                    'findings' => [],
                    'edge_provider' => ['name' => 'Unknown'],
                ],
                'meta' => ['target' => 'example.com'],
                'file_name' => 'port-checker-test.pdf',
            ]);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }
}
