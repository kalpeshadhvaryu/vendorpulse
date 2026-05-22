<?php

namespace App\ExperienceMonitoring\Jobs;

use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunExperienceMonitoringTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries;

    public function __construct(
        public string $testId,
    ) {
        $this->tries = max(1, (int) config('experience-monitoring.run_job_tries', 3));
        $this->onQueue((string) config('experience-monitoring.queue', 'experience-monitoring'));
    }

    public function handle(
        ExperienceMonitoringRepositoryInterface $repository,
    ): void {
        $test = $repository->findTest($this->testId);
        if (! $test || ! $test->enabled) {
            return;
        }

        $maxConfigured = max(1, (int) config('experience-monitoring.max_concurrent_sessions', 5));
        $concurrentSessions = min(max(1, (int) ($test->concurrent_sessions ?? 1)), $maxConfigured);
        $queue = (string) config('experience-monitoring.queue', 'experience-monitoring');

        for ($session = 1; $session <= $concurrentSessions; $session++) {
            RunExperienceMonitoringSessionJob::dispatch($this->testId, $session)->onQueue($queue);
        }
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
            (new WithoutOverlapping('experience-monitoring-test:'.$this->testId))
                ->releaseAfter(180)
                ->expireAfter(360),
        ];
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
        return ['experience-monitoring', 'test:'.$this->testId];
    }
}
