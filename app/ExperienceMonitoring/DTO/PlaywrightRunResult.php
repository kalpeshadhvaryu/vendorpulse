<?php

namespace App\ExperienceMonitoring\DTO;

class PlaywrightRunResult
{
    public function __construct(
        public string $status,
        public ?int $loginDurationMs,
        public ?int $dashboardLoadDurationMs,
        public ?int $totalDurationMs,
        public ?int $httpStatus,
        public array $httpStatusCodes,
        public array $responseTimes,
        public array $consoleErrors,
        public array $failedRequests,
        public array $browserLogs,
        public ?string $errorMessage,
        public ?string $screenshotBase64,
        public ?string $screenshotMime,
        public ?string $startedAt,
        public ?string $finishedAt,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            status: (string) ($payload['status'] ?? 'error'),
            loginDurationMs: isset($payload['login_duration_ms']) ? (int) $payload['login_duration_ms'] : null,
            dashboardLoadDurationMs: isset($payload['dashboard_load_duration_ms']) ? (int) $payload['dashboard_load_duration_ms'] : null,
            totalDurationMs: isset($payload['total_duration_ms']) ? (int) $payload['total_duration_ms'] : null,
            httpStatus: isset($payload['http_status']) ? (int) $payload['http_status'] : null,
            httpStatusCodes: is_array($payload['http_status_codes'] ?? null) ? $payload['http_status_codes'] : [],
            responseTimes: is_array($payload['response_times'] ?? null) ? $payload['response_times'] : [],
            consoleErrors: is_array($payload['console_errors'] ?? null) ? $payload['console_errors'] : [],
            failedRequests: is_array($payload['failed_requests'] ?? null) ? $payload['failed_requests'] : [],
            browserLogs: is_array($payload['browser_logs'] ?? null) ? $payload['browser_logs'] : [],
            errorMessage: isset($payload['error_message']) ? (string) $payload['error_message'] : null,
            screenshotBase64: isset($payload['screenshot_base64']) ? (string) $payload['screenshot_base64'] : null,
            screenshotMime: isset($payload['screenshot_mime']) ? (string) $payload['screenshot_mime'] : 'image/png',
            startedAt: isset($payload['started_at']) ? (string) $payload['started_at'] : null,
            finishedAt: isset($payload['finished_at']) ? (string) $payload['finished_at'] : null,
        );
    }
}
