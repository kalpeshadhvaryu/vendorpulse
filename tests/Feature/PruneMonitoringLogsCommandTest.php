<?php

namespace Tests\Feature;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\Models\Organization;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneMonitoringLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_deletes_logs_older_than_retention_days(): void
    {
        $org = Organization::factory()->create();
        $check = MonitoringCheck::factory()->create([
            'organization_id' => $org->id,
        ]);

        $old = MonitoringLog::create([
            'monitoring_check_id' => $check->id,
            'organization_id' => $org->id,
            'status' => MonitoringLogStatus::Ok,
            'message' => 'old',
            'meta' => null,
        ]);
        DB::table('monitoring_logs')->where('id', $old->id)->update([
            'created_at' => Carbon::now()->subDays(100),
            'updated_at' => Carbon::now()->subDays(100),
        ]);

        $recent = MonitoringLog::create([
            'monitoring_check_id' => $check->id,
            'organization_id' => $org->id,
            'status' => MonitoringLogStatus::Ok,
            'message' => 'recent',
            'meta' => null,
        ]);

        $this->artisan('monitoring:prune-logs', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('monitoring_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('monitoring_logs', ['id' => $recent->id]);
    }

    public function test_dry_run_does_not_delete(): void
    {
        $org = Organization::factory()->create();
        $check = MonitoringCheck::factory()->create([
            'organization_id' => $org->id,
        ]);

        $old = MonitoringLog::create([
            'monitoring_check_id' => $check->id,
            'organization_id' => $org->id,
            'status' => MonitoringLogStatus::Failed,
            'message' => 'old fail',
            'meta' => null,
        ]);
        DB::table('monitoring_logs')->where('id', $old->id)->update([
            'created_at' => Carbon::now()->subDays(120),
            'updated_at' => Carbon::now()->subDays(120),
        ]);

        $this->artisan('monitoring:prune-logs', ['--days' => 90, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('monitoring_logs', ['id' => $old->id]);
    }
}
