<?php

namespace App\SiteMonitoring\Jobs;

use App\SiteMonitoring\Services\MonitoringExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunMonitoringCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries;

    public function __construct(
        public string $monitoringCheckId,
    ) {
        $this->tries = max(1, (int) config('site-monitoring.run_job_tries', 3));
        $this->onQueue((string) config('site-monitoring.queue', 'site-monitoring'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $parts = explode(',', (string) config('site-monitoring.run_job_backoff_seconds', '15,60,180'));

        return array_values(array_filter(array_map(
            static fn (string $v): int => max(1, (int) trim($v)),
            $parts
        )));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('monitoring-check:'.$this->monitoringCheckId))
                ->releaseAfter(180)
                ->expireAfter(300),
        ];
    }

    public function handle(MonitoringExecutionService $execution): void
    {
        $execution->execute($this->monitoringCheckId, [
            'queue_attempt' => $this->attempts(),
            'queue_connection' => $this->job?->getConnectionName(),
            'queue_job_id' => $this->job?->getJobId(),
        ]);
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
        return ['site-monitoring', 'monitoring-check:'.$this->monitoringCheckId];
    }
}
