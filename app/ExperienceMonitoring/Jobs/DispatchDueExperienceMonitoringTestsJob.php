<?php

namespace App\ExperienceMonitoring\Jobs;

use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchDueExperienceMonitoringTestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct()
    {
        $this->onQueue((string) config('experience-monitoring.dispatch_queue', 'default'));
        $this->tries = max(1, (int) config('experience-monitoring.dispatch_job_tries', 3));
    }

    public function handle(ExperienceMonitoringRepositoryInterface $repository): void
    {
        $queue = (string) config('experience-monitoring.queue', 'experience-monitoring');

        $repository->dueEnabledTestIds(Carbon::now(), 1000)
            ->each(fn (string $id) => RunExperienceMonitoringTestJob::dispatch($id)->onQueue($queue));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $parts = explode(',', (string) config('experience-monitoring.dispatch_job_backoff_seconds', '30,120'));

        return array_values(array_filter(array_map(
            static fn (string $v): int => max(1, (int) trim($v)),
            $parts
        )));
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
        return ['experience-monitoring', 'dispatch-due-tests'];
    }
}
