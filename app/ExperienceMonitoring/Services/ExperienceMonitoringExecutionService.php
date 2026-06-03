<?php

namespace App\ExperienceMonitoring\Services;

use App\ExperienceMonitoring\DTO\PlaywrightRunInput;
use App\ExperienceMonitoring\Enums\ExperienceMonitoringRunStatus;
use App\Models\ExperienceMonitoringTest;
use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExperienceMonitoringExecutionService
{
    public function __construct(
        protected ExperienceMonitoringRepositoryInterface $repository,
        protected PlaywrightRunnerService $runner,
        protected ExperienceMonitoringAlertService $alerts,
    ) {}

    public function execute(string $testId, int $sessionIndex = 1): void
    {
        $test = $this->repository->findTest($testId);

        if (! $test || ! $test->enabled) {
            if (! $test) {
                $this->markExecutionFailed($testId, $sessionIndex, 'Experience monitoring test not found.');
            } elseif (! $test->enabled) {
                $this->markExecutionFailed($testId, $sessionIndex, 'Experience monitoring test is disabled.');
            }

            return;
        }

        try {
            $result = $this->runner->run(PlaywrightRunInput::fromTest($test, $sessionIndex));
            $run = $this->repository->createRun([
                'experience_monitoring_test_id' => $test->id,
                'organization_id' => $test->organization_id,
                'session_index' => $sessionIndex,
                'status' => $result->status,
                'login_duration_ms' => $result->loginDurationMs,
                'dashboard_load_duration_ms' => $result->dashboardLoadDurationMs,
                'total_duration_ms' => $result->totalDurationMs,
                'http_status' => $result->httpStatus,
                'failed_requests_count' => count($result->failedRequests),
                'js_errors_count' => count($result->consoleErrors),
                'http_status_codes' => $result->httpStatusCodes,
                'response_times' => $result->responseTimes,
                'browser_logs' => $result->browserLogs,
                'error_message' => $result->errorMessage,
                'started_at' => $result->startedAt,
                'finished_at' => $result->finishedAt,
            ]);

            $screenshotPath = $this->persistScreenshot($test, $run->id, $result->screenshotBase64, $result->screenshotMime);
            if ($screenshotPath !== null) {
                $run->screenshot_path = $screenshotPath;
                $run->save();

                $this->repository->createScreenshot([
                    'experience_monitoring_run_id' => $run->id,
                    'organization_id' => $test->organization_id,
                    'path' => $screenshotPath,
                    'mime_type' => $result->screenshotMime ?? 'image/png',
                    'size_bytes' => Storage::disk((string) config('experience-monitoring.screenshots_disk', 'local'))->size($screenshotPath),
                    'captured_at' => now(),
                ]);
            }

            $metricRows = [
                ['key' => 'login_duration_ms', 'value' => $result->loginDurationMs, 'unit' => 'ms'],
                ['key' => 'dashboard_load_duration_ms', 'value' => $result->dashboardLoadDurationMs, 'unit' => 'ms'],
                ['key' => 'total_duration_ms', 'value' => $result->totalDurationMs, 'unit' => 'ms'],
                ['key' => 'failed_requests_count', 'value' => count($result->failedRequests), 'unit' => 'count'],
                ['key' => 'js_errors_count', 'value' => count($result->consoleErrors), 'unit' => 'count'],
            ];

            foreach ($metricRows as $metric) {
                $this->repository->createRunMetric([
                    'experience_monitoring_run_id' => $run->id,
                    'organization_id' => $test->organization_id,
                    'metric_key' => $metric['key'],
                    'metric_value' => $metric['value'],
                    'unit' => $metric['unit'],
                    'recorded_at' => now(),
                ]);
            }

            foreach ($result->consoleErrors as $entry) {
                $this->repository->createExecutionLog([
                    'experience_monitoring_run_id' => $run->id,
                    'organization_id' => $test->organization_id,
                    'source' => 'console',
                    'level' => 'error',
                    'message' => (string) ($entry['message'] ?? 'Console error'),
                    'context' => $entry,
                    'occurred_at' => now(),
                ]);
            }

            foreach ($result->failedRequests as $entry) {
                $this->repository->createExecutionLog([
                    'experience_monitoring_run_id' => $run->id,
                    'organization_id' => $test->organization_id,
                    'source' => 'network',
                    'level' => 'error',
                    'message' => (string) ($entry['url'] ?? 'Failed request'),
                    'context' => $entry,
                    'occurred_at' => now(),
                ]);
            }

            $effectiveStatus = $this->resolveEffectiveStatus($result->status, $result->dashboardLoadDurationMs, count($result->consoleErrors));
            $run->status = $effectiveStatus->value;
            $run->save();

            $test->last_status = $effectiveStatus->value;
            $test->last_error = $result->errorMessage;
            $test->last_run_at = now();
            $test->next_run_at = now()->addSeconds(max(60, (int) $test->interval_seconds));
            $test->save();

            $this->alerts->dispatchAlerts($test, $run->fresh());
        } catch (Throwable $e) {
            $this->markExecutionFailed($testId, $sessionIndex, $e->getMessage());
        }
    }

    public function markExecutionFailed(string $testId, int $sessionIndex, string $message): void
    {
        $test = $this->repository->findTest($testId);

        Log::error('Experience monitoring execution failed.', [
            'test_id' => $testId,
            'session_index' => $sessionIndex,
            'error' => $message,
        ]);

        if (! $test instanceof ExperienceMonitoringTest) {
            return;
        }

        $this->repository->createRun([
            'experience_monitoring_test_id' => $test->id,
            'organization_id' => $test->organization_id,
            'session_index' => $sessionIndex,
            'status' => ExperienceMonitoringRunStatus::Error->value,
            'error_message' => $message,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $test->last_status = ExperienceMonitoringRunStatus::Error->value;
        $test->last_error = $message;
        $test->last_run_at = now();
        $test->next_run_at = now()->addSeconds(max(60, (int) $test->interval_seconds));
        $test->save();
    }

    private function resolveEffectiveStatus(string $status, ?int $dashboardLoadDurationMs, int $consoleErrorCount): ExperienceMonitoringRunStatus
    {
        $base = ExperienceMonitoringRunStatus::tryFrom($status) ?? ExperienceMonitoringRunStatus::Error;

        if ($base === ExperienceMonitoringRunStatus::Ok && $dashboardLoadDurationMs !== null) {
            if ($dashboardLoadDurationMs >= max(1, (int) config('experience-monitoring.slow_dashboard_threshold_ms', 8000))) {
                return ExperienceMonitoringRunStatus::SlowDashboard;
            }
        }

        if ($base === ExperienceMonitoringRunStatus::Ok && $consoleErrorCount > 0) {
            return ExperienceMonitoringRunStatus::JsError;
        }

        return $base;
    }

    private function persistScreenshot(ExperienceMonitoringTest $test, string $runId, ?string $screenshotBase64, ?string $mimeType): ?string
    {
        if (! $screenshotBase64) {
            return null;
        }

        $decoded = base64_decode($screenshotBase64, true);
        if ($decoded === false) {
            return null;
        }

        $extension = $mimeType === 'image/jpeg' ? 'jpg' : 'png';
        $path = trim((string) config('experience-monitoring.screenshots_root', 'experience-monitoring/screenshots'), '/').'/'.
            $test->organization_id.'/'.
            Carbon::now()->format('Y/m/d').'/'.
            Str::slug($test->name).'-'.$runId.'.'.$extension;

        $disk = Storage::disk((string) config('experience-monitoring.screenshots_disk', 'local'));
        $disk->put($path, $decoded);
        $this->ensureStoragePathReadable($disk->path($path));

        return $path;
    }

    private function ensureStoragePathReadable(string $absolutePath): void
    {
        if (! is_file($absolutePath)) {
            return;
        }

        @chmod($absolutePath, 0644);

        $dir = dirname($absolutePath);
        $storageRoot = storage_path('app');

        while (str_starts_with($dir, $storageRoot) && $dir !== $storageRoot) {
            if (is_dir($dir)) {
                @chmod($dir, 0755);
            }

            $dir = dirname($dir);
        }
    }
}
