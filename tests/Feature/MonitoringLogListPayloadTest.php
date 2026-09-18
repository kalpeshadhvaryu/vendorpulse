<?php

namespace Tests\Feature;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\Models\Organization;
use App\Models\User;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonitoringLogListPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_list_omits_heavy_http_meta_fields(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create([
            'default_organization_id' => $org->id,
        ]);
        $user->organizations()->attach($org->id, ['role' => 'owner']);

        $check = MonitoringCheck::factory()->create([
            'organization_id' => $org->id,
            'type' => 'uptime',
        ]);

        MonitoringLog::create([
            'monitoring_check_id' => $check->id,
            'organization_id' => $org->id,
            'status' => MonitoringLogStatus::Ok,
            'http_status' => 200,
            'response_time_ms' => 42,
            'message' => 'ok',
            'meta' => [
                'http_response_body_preview' => str_repeat('x', 2000),
                'http_response_headers' => ['content-type' => 'text/html'],
                'probe' => 'uptime',
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'X-Organization-Id' => (string) $org->id,
        ])->getJson("/api/v1/monitoring-checks/{$check->id}/logs?per_page=10");

        $response->assertOk();
        $item = $response->json('data.0');
        $this->assertIsArray($item);
        $this->assertArrayNotHasKey('http_response_body_preview', $item['meta'] ?? []);
        $this->assertArrayNotHasKey('http_response_headers', $item['meta'] ?? []);
        $this->assertSame('uptime', $item['meta']['probe'] ?? null);
    }
}
