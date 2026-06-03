<?php

namespace App\ExperienceMonitoring\Jobs;

use App\ExperienceMonitoring\Services\ExperienceMonitoringExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunExperienceMonitoringSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries;

    public function __construct(
        public string $testId,
        public int $sessionIndex,
    ) {
        $this->timeout = max(60, (int) config('experience-monitoring.run_job_timeout_seconds', 300));
        $this->tries = max(1, (int) config('experience-monitoring.run_job_tries', 3));
        $this->onQueue((string) config('experience-monitoring.queue', 'experience-monitoring'));
    }

    public function handle(ExperienceMonitoringExecutionService $execution): void
    {
        $execution->execute($this->testId, $this->sessionIndex);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $parts = explode(',', (string) config('experience-monitoring.run_job_backoff_seconds', '15,60,180'));

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
            (new WithoutOverlapping('experience-monitoring-test-session:'.$this->testId.':'.$this->sessionIndex))
                ->releaseAfter(max(180, $this->timeout))
                ->expireAfter(max(360, $this->timeout * 2)),
        ];
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }

        app(ExperienceMonitoringExecutionService::class)->markExecutionFailed(
            $this->testId,
            $this->sessionIndex,
            $exception?->getMessage() ?? 'Experience monitoring job failed after retries.',
        );
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'experience-monitoring',
            'test:'.$this->testId,
            'session:'.$this->sessionIndex,
        ];
    }
}
