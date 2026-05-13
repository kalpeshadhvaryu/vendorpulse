<?php

use App\EmailMonitoring\Jobs\DispatchPollEmailMailboxesJob;
use App\SiteMonitoring\Jobs\DispatchDueMonitoringChecksJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
