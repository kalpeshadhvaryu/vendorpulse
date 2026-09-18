<?php

namespace App\SiteMonitoring\Jobs;

use App\Models\MonitoringCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
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
        $maxDepth = max(1, (int) config('site-monitoring.dispatch_max_queue_depth', 2000));
        $batchSize = max(1, (int) config('site-monitoring.dispatch_batch_size', 200));

        $pending = (int) Redis::connection()->llen('queues:'.$queue);
        if ($pending >= $maxDepth) {
            Log::warning('site-monitoring dispatch skipped: queue depth too high', [
                'queue' => $queue,
                'pending' => $pending,
                'max_depth' => $maxDepth,
            ]);

            return;
        }

        $remainingCapacity = max(0, $maxDepth - $pending);
        $limit = min($batchSize, $remainingCapacity);

        if ($limit < 1) {
            return;
        }

        $checks = MonitoringCheck::query()
            ->withoutGlobalScopes()
            ->where('enabled', true)
            ->where(function ($q): void {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'interval_seconds']);

        foreach ($checks as $check) {
            // Reserve the next slot immediately so a slow/down Horizon cannot
            // re-enqueue the same due check every minute (queue storm).
            $interval = max(60, (int) $check->interval_seconds);
            MonitoringCheck::query()
                ->withoutGlobalScopes()
                ->whereKey($check->id)
                ->update([
                    'next_run_at' => now()->addSeconds($interval),
                ]);

            RunMonitoringCheckJob::dispatch((string) $check->id)->onQueue($queue);
        }
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
