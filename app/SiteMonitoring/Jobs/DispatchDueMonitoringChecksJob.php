<?php

namespace App\SiteMonitoring\Jobs;

use App\Models\MonitoringCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchDueMonitoringChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct()
    {
        $this->onQueue('default');
        $this->tries = max(1, (int) config('site-monitoring.dispatch_job_tries', 3));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $parts = explode(',', (string) config('site-monitoring.dispatch_job_backoff_seconds', '30,120'));

        return array_values(array_filter(array_map(
            static fn (string $v): int => max(1, (int) trim($v)),
            $parts
        )));
    }

    public function handle(): void
    {
        $queue = (string) config('site-monitoring.queue', 'site-monitoring');

        MonitoringCheck::query()
            ->withoutGlobalScopes()
            ->where('enabled', true)
            ->where(function ($q): void {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(1000)
            ->pluck('id')
            ->each(fn (string $id) => RunMonitoringCheckJob::dispatch($id)->onQueue($queue));
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['site-monitoring', 'dispatch-due-checks'];
    }
}
