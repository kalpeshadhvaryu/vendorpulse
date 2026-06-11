<?php

use App\EmailMonitoring\Jobs\DispatchPollEmailMailboxesJob;
use App\ExperienceMonitoring\Jobs\DispatchDueExperienceMonitoringTestsJob;
use App\Models\MonitoringCheck;
use App\Models\Organization;
use App\SiteMonitoring\Jobs\DispatchDueMonitoringChecksJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::call(static function (): void {
    DispatchPollEmailMailboxesJob::dispatch();
})
    ->everyFifteenMinutes()
    ->name('email-monitoring:dispatch-mailbox-polls')
    ->withoutOverlapping(10);

/*
| Site monitoring: every minute the scheduler enqueues DispatchDueMonitoringChecksJob (default queue).
| Horizon workers must process both "default" (dispatch fan-out) and SITE_MONITORING_QUEUE (per-check runs).
*/
Schedule::call(static function (): void {
    DispatchDueMonitoringChecksJob::dispatch();
})
    ->everyMinute()
    ->name('site-monitoring:dispatch-due-checks')
    ->withoutOverlapping(2);

Schedule::call(static function (): void {
    DispatchDueExperienceMonitoringTestsJob::dispatch();
})
    ->everyMinute()
    ->name('experience-monitoring:dispatch-due-tests')
    ->withoutOverlapping(2);

Schedule::command('vapt:speedtest-alerts')
    ->everyFifteenMinutes()
    ->name('vapt:speedtest-alerts')
    ->withoutOverlapping(10);

Artisan::command('monitoring:reassign-check
    {checkId : Monitoring check UUID}
    {fromOrgId : Current organization UUID}
    {toOrgId : Target organization UUID}
    {--execute : Apply changes (default is dry-run)}',
    function (string $checkId, string $fromOrgId, string $toOrgId): int {
        $execute = (bool) $this->option('execute');

        if ($fromOrgId === $toOrgId) {
            $this->error('Source and target organizations are the same. Nothing to do.');

            return 1;
        }

        $fromOrgExists = Organization::query()->withTrashed()->whereKey($fromOrgId)->exists();
        $toOrgExists = Organization::query()->withTrashed()->whereKey($toOrgId)->exists();

        if (! $fromOrgExists) {
            $this->error("Source organization not found: {$fromOrgId}");

            return 1;
        }

        if (! $toOrgExists) {
            $this->error("Target organization not found: {$toOrgId}");

            return 1;
        }

        $check = MonitoringCheck::query()->withTrashed()->find($checkId);
        if (! $check) {
            $this->error("Monitoring check not found: {$checkId}");

            return 1;
        }

        if ((string) $check->organization_id !== $fromOrgId) {
            $this->error(
                'Check does not belong to source organization. Actual organization_id: '.(string) $check->organization_id
            );

            return 1;
        }

        $vendorOrgId = null;
        if ($check->vendor_id) {
            $vendorOrgId = DB::table('vendors')->where('id', $check->vendor_id)->value('company_id');
        }

        if ($vendorOrgId && (string) $vendorOrgId !== $toOrgId) {
            $this->error('Safety check failed: check vendor belongs to a different organization (company_id mismatch).');
            $this->line('Either move/relink the vendor first, or clear vendor_id on this monitoring check before reassigning.');

            return 1;
        }

        $monitoringLogsTotal = DB::table('monitoring_logs')
            ->where('monitoring_check_id', $checkId)
            ->count();
        $monitoringLogsDrift = DB::table('monitoring_logs')
            ->where('monitoring_check_id', $checkId)
            ->where('organization_id', '!=', $fromOrgId)
            ->count();

        $socialAccountsTotal = DB::table('domain_social_accounts')
            ->where('monitoring_check_id', $checkId)
            ->count();
        $socialAccountsDrift = DB::table('domain_social_accounts')
            ->where('monitoring_check_id', $checkId)
            ->where('organization_id', '!=', $fromOrgId)
            ->count();

        if ($monitoringLogsDrift > 0 || $socialAccountsDrift > 0) {
            $this->error('Safety check failed: related records already contain organization mismatches.');
            $this->table(
                ['table', 'mismatched_rows'],
                [
                    ['monitoring_logs', $monitoringLogsDrift],
                    ['domain_social_accounts', $socialAccountsDrift],
                ]
            );

            return 1;
        }

        $this->table(
            ['item', 'value'],
            [
                ['mode', $execute ? 'EXECUTE' : 'DRY-RUN'],
                ['check_id', $checkId],
                ['check_name', (string) $check->name],
                ['from_org_id', $fromOrgId],
                ['to_org_id', $toOrgId],
                ['monitoring_logs_to_move', $monitoringLogsTotal],
                ['domain_social_accounts_to_move', $socialAccountsTotal],
            ]
        );

        if (! $execute) {
            $this->info('Dry-run complete. Re-run with --execute to apply within one transaction.');

            return 0;
        }

        DB::transaction(function () use ($checkId, $fromOrgId, $toOrgId): void {
            $updatedChecks = DB::table('monitoring_checks')
                ->where('id', $checkId)
                ->where('organization_id', $fromOrgId)
                ->update([
                    'organization_id' => $toOrgId,
                    'updated_at' => now(),
                ]);

            if ($updatedChecks !== 1) {
                throw new RuntimeException('Monitoring check update failed unexpectedly.');
            }

            DB::table('monitoring_logs')
                ->where('monitoring_check_id', $checkId)
                ->where('organization_id', $fromOrgId)
                ->update([
                    'organization_id' => $toOrgId,
                ]);

            DB::table('domain_social_accounts')
                ->where('monitoring_check_id', $checkId)
                ->where('organization_id', $fromOrgId)
                ->update([
                    'organization_id' => $toOrgId,
                ]);
        });

        $this->info('Reassignment completed successfully.');

        return 0;
    }
)->purpose('Safely reassign a monitoring check and its related records to another organization.');
