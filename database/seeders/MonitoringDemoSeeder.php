<?php

namespace Database\Seeders;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\Models\Organization;
use App\Models\User;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use Illuminate\Database\Seeder;

/**
 * Idempotent sample checks for local QA and dashboards.
 *
 * Removes any existing rows whose name starts with "[Demo] " for Demo Organization,
 * then recreates three checks (healthy HTTPS, failing HTTPS, SSL) plus recent logs.
 *
 * Run: php artisan db:seed --class=MonitoringDemoSeeder
 */
class MonitoringDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('name', 'Demo Organization')->first();
        if (! $org) {
            $this->command?->warn('MonitoringDemoSeeder: no organization named "Demo Organization" — skipped.');

            return;
        }

        $actorId = User::query()->where('email', 'test@example.com')->value('id');

        $this->purgeDemoChecks($org->id);

        $definitions = [
            [
                'name' => '[Demo] Healthy endpoint',
                'type' => 'https',
                'endpoint' => 'https://example.com',
                'configuration' => ['method' => 'HEAD', 'expected_status' => 200, 'timeout_seconds' => 15],
                'interval_seconds' => 300,
                'enabled' => true,
                'last_status' => 'ok',
                'last_message' => 'HTTP 200',
                'last_http_status' => 200,
                'last_response_time_ms' => 87,
                'consecutive_failures' => 0,
                'last_run_at' => now()->subMinutes(2),
                'next_run_at' => now()->addMinutes(3),
                'logs' => [
                    [
                        'status' => MonitoringLogStatus::Ok,
                        'http_status' => 200,
                        'response_time_ms' => 92,
                        'message' => 'HTTP 200',
                        'meta' => ['url' => 'https://example.com', 'method' => 'HEAD'],
                    ],
                    [
                        'status' => MonitoringLogStatus::Ok,
                        'http_status' => 200,
                        'response_time_ms' => 87,
                        'message' => 'HTTP 200',
                        'meta' => null,
                    ],
                ],
            ],
            [
                'name' => '[Demo] Failing HTTP (expects 200)',
                'type' => 'https',
                'endpoint' => 'https://httpstat.us/500',
                'configuration' => ['method' => 'HEAD', 'expected_status' => 200, 'timeout_seconds' => 15],
                'interval_seconds' => 300,
                'enabled' => true,
                'last_status' => 'failed',
                'last_message' => 'Unexpected HTTP status 500 (expected: 200)',
                'last_http_status' => 500,
                'last_response_time_ms' => 210,
                'consecutive_failures' => 2,
                'last_run_at' => now()->subMinutes(5),
                'next_run_at' => now()->addMinute(),
                'logs' => [
                    [
                        'status' => MonitoringLogStatus::Failed,
                        'http_status' => 500,
                        'response_time_ms' => 198,
                        'message' => 'Unexpected HTTP status 500 (expected: 200)',
                        'meta' => ['url' => 'https://httpstat.us/500', 'method' => 'HEAD'],
                    ],
                    [
                        'status' => MonitoringLogStatus::Failed,
                        'http_status' => 500,
                        'response_time_ms' => 210,
                        'message' => 'Unexpected HTTP status 500 (expected: 200)',
                        'meta' => null,
                    ],
                ],
            ],
            [
                'name' => '[Demo] SSL certificate',
                'type' => 'ssl',
                'endpoint' => 'https://example.com',
                'configuration' => ['port' => 443, 'timeout_seconds' => 15, 'verify_ssl' => true],
                'interval_seconds' => 3600,
                'enabled' => true,
                'last_status' => 'ok',
                'last_message' => 'Certificate valid',
                'last_http_status' => null,
                'last_response_time_ms' => 145,
                'consecutive_failures' => 0,
                'last_run_at' => now()->subHour(),
                'next_run_at' => now()->addHour(),
                'logs' => [
                    [
                        'status' => MonitoringLogStatus::Ok,
                        'http_status' => null,
                        'response_time_ms' => 150,
                        'message' => 'Certificate valid',
                        'meta' => ['host' => 'example.com', 'port' => 443],
                    ],
                ],
            ],
        ];

        foreach ($definitions as $def) {
            $logs = $def['logs'];
            unset($def['logs']);

            $def['organization_id'] = $org->id;
            $def['vendor_id'] = null;
            $def['created_by'] = $actorId;
            $def['updated_by'] = $actorId;

            /** @var MonitoringCheck $check */
            $check = MonitoringCheck::query()->create($def);

            foreach ($logs as $log) {
                MonitoringLog::query()->create([
                    'monitoring_check_id' => $check->id,
                    'organization_id' => $org->id,
                    'status' => $log['status'],
                    'http_status' => $log['http_status'] ?? null,
                    'response_time_ms' => $log['response_time_ms'] ?? null,
                    'message' => $log['message'] ?? null,
                    'meta' => $log['meta'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command?->info('MonitoringDemoSeeder: created [Demo] monitoring checks for Demo Organization.');
    }

    private function purgeDemoChecks(string $organizationId): void
    {
        $checks = MonitoringCheck::withoutGlobalScopes()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->where('name', 'like', '[Demo] %')
            ->get();

        foreach ($checks as $check) {
            $check->monitoringLogs()->delete();
            $check->forceDelete();
        }
    }
}
