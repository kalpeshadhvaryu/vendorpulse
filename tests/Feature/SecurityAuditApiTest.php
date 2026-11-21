<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\Vapt\SecurityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authorization_flag(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id);
        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/vapt-web-health/security-audit', [
                'target_url' => 'https://example.com',
                'depth' => 'standard',
            ])
            ->assertStatus(422);
    }

    public function test_returns_security_audit_report(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id);
        Sanctum::actingAs($user);

        $fake = [
            'target_url' => 'https://example.com',
            'final_url' => 'https://example.com',
            'host' => 'example.com',
            'depth' => 'standard',
            'scanned_at' => now()->toIso8601String(),
            'http_status' => 200,
            'severity' => 'S3',
            'risk_score' => 8,
            'summary' => ['total_checks' => 1, 'passed' => 1, 'warnings' => 0, 'failed' => 0],
            'categories' => [
                'security_misconfiguration' => ['pass' => 1, 'warning' => 0, 'fail' => 0],
            ],
            'findings' => [[
                'category' => 'security_misconfiguration',
                'title' => 'Core security headers present',
                'status' => 'pass',
                'severity' => 'S3',
                'explanation' => 'ok',
                'suggestion' => 'ok',
                'evidence' => [],
            ]],
            'scope_note' => 'test',
        ];

        $this->mock(SecurityAuditService::class, function ($mock) use ($fake): void {
            $mock->shouldReceive('audit')
                ->once()
                ->with('https://example.com', 'standard')
                ->andReturn($fake);
        });

        $this->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/vapt-web-health/security-audit', [
                'target_url' => 'https://example.com',
                'authorized' => true,
                'depth' => 'standard',
            ])
            ->assertOk()
            ->assertJsonPath('data.host', 'example.com')
            ->assertJsonPath('data.severity', 'S3');
    }
}
