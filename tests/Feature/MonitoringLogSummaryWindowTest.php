<?php

namespace Tests\Feature;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\Models\Organization;
use App\Services\MonitoringCheckService;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonitoringLogSummaryWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_does_not_count_time_before_check_existed_as_unknown(): void
    {
        $org = Organization::factory()->create();
        $checkStart = Carbon::parse('2026-02-10 12:00:00');
        $check = MonitoringCheck::factory()->create([
            'organization_id' => $org->id,
        ]);
        DB::table('monitoring_checks')->where('id', $check->id)->update([
            'created_at' => $checkStart,
            'updated_at' => $checkStart,
        ]);
        $check->refresh();

        $logTime = Carbon::parse('2026-02-11 12:00:00');
        $log = MonitoringLog::create([
            'monitoring_check_id' => $check->id,
            'organization_id' => $org->id,
            'status' => MonitoringLogStatus::Ok,
            'http_status' => 200,
            'response_time_ms' => 50,
            'message' => 'ok',
            'meta' => null,
        ]);
        DB::table('monitoring_logs')->where('id', $log->id)->update([
            'created_at' => $logTime,
            'updated_at' => $logTime,
        ]);

        $check->refresh();

        $from = Carbon::parse('2026-02-01 00:00:00');
        $to = Carbon::parse('2026-02-12 23:59:59');

        $summary = app(MonitoringCheckService::class)->summarizeLogsWindow($check, $from, $to);

        $this->assertSame(0, $summary['duration_seconds']['unknown']);
        $this->assertGreaterThan(0, $summary['duration_seconds']['up']);
        $this->assertEquals(1.0, $summary['uptime_ratio']);
        $this->assertTrue(Carbon::parse($summary['window_from'])->equalTo($checkStart));
    }

    public function test_summary_with_no_logs_in_window_is_all_unknown_and_null_ratio(): void
    {
        $org = Organization::factory()->create();
        $checkStart = Carbon::parse('2026-03-01 10:00:00');
        $check = MonitoringCheck::factory()->create([
            'organization_id' => $org->id,
        ]);
        DB::table('monitoring_checks')->where('id', $check->id)->update([
            'created_at' => $checkStart,
            'updated_at' => $checkStart,
        ]);

        $check->refresh();

        $from = Carbon::parse('2026-03-01 00:00:00');
        $to = Carbon::parse('2026-03-02 00:00:00');

        $summary = app(MonitoringCheckService::class)->summarizeLogsWindow($check, $from, $to);

        $this->assertSame(0, $summary['log_count_in_window']);
        $this->assertNull($summary['uptime_ratio']);
        $this->assertSame(0, $summary['duration_seconds']['up']);
        $expectedSpan = (int) round($checkStart->diffInSeconds($to));
        $this->assertEquals($expectedSpan, $summary['window_span_seconds']);
        $this->assertEquals($expectedSpan, $summary['duration_seconds']['unknown']);
    }
}
