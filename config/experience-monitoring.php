<?php

return [
    'queue' => env('EXPERIENCE_MONITORING_QUEUE', 'experience-monitoring'),

    'dispatch_queue' => env('EXPERIENCE_MONITORING_DISPATCH_QUEUE', 'default'),

    'runner_command' => env('EXPERIENCE_MONITORING_RUNNER_COMMAND', 'node'),

    'runner_script' => env('EXPERIENCE_MONITORING_RUNNER_SCRIPT', base_path('scripts/experience-monitoring/runner.mjs')),

    'runner_timeout_seconds' => (int) env('EXPERIENCE_MONITORING_RUNNER_TIMEOUT_SECONDS', 120),

    'max_concurrent_sessions' => (int) env('EXPERIENCE_MONITORING_MAX_CONCURRENT_SESSIONS', 5),

    'slow_dashboard_threshold_ms' => (int) env('EXPERIENCE_MONITORING_SLOW_DASHBOARD_THRESHOLD_MS', 8000),

    'run_job_tries' => (int) env('EXPERIENCE_MONITORING_RUN_JOB_TRIES', 3),

    'run_job_backoff_seconds' => env('EXPERIENCE_MONITORING_RUN_JOB_BACKOFF', '15,60,180'),

    'dispatch_job_tries' => (int) env('EXPERIENCE_MONITORING_DISPATCH_JOB_TRIES', 3),

    'dispatch_job_backoff_seconds' => env('EXPERIENCE_MONITORING_DISPATCH_JOB_BACKOFF', '30,120'),

    'screenshots_disk' => env('EXPERIENCE_MONITORING_SCREENSHOTS_DISK', 'local'),

    'screenshots_root' => env('EXPERIENCE_MONITORING_SCREENSHOTS_ROOT', 'experience-monitoring/screenshots'),

    // Playwright browsers installed in the worker image (Docker installs Chromium only).
    'allowed_browsers' => array_values(array_filter(array_map(
        trim(...),
        explode(',', env('EXPERIENCE_MONITORING_ALLOWED_BROWSERS', 'chromium')),
    ))),

    'default_browser' => env('EXPERIENCE_MONITORING_DEFAULT_BROWSER', 'chromium'),
];
