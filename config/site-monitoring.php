<?php

return [

    'queue' => env('SITE_MONITORING_QUEUE', 'site-monitoring'),

    'ssl_warning_days' => (int) env('SITE_MONITORING_SSL_WARNING_DAYS', 30),

    'domain_warning_days' => (int) env('SITE_MONITORING_DOMAIN_WARNING_DAYS', 30),

    /** Notify on uptime failure after this many consecutive failures (1 = first failure). */
    'uptime_failure_notify_after' => (int) env('SITE_MONITORING_UPTIME_NOTIFY_AFTER_FAILURES', 1),

    /** Throttle repeated SSL expiry alerts per check (seconds). */
    'ssl_alert_throttle_seconds' => (int) env('SITE_MONITORING_SSL_ALERT_THROTTLE', 86400),

    /** Throttle repeated domain expiry alerts per check (seconds). */
    'domain_alert_throttle_seconds' => (int) env('SITE_MONITORING_DOMAIN_ALERT_THROTTLE', 86400),

    /**
     * Domain expiry provider: "rdap" (public RDAP via rdap.org bootstrap) or "null".
     */
    'domain_provider' => env('SITE_MONITORING_DOMAIN_PROVIDER', 'rdap'),

    'rdap_timeout_seconds' => (int) env('SITE_MONITORING_RDAP_TIMEOUT', 25),

    'rdap_retries' => (int) env('SITE_MONITORING_RDAP_RETRIES', 2),

    /**
     * In-process HTTP retries for uptime checks (connection / timeout blips).
     */
    'uptime_http_retries' => (int) env('SITE_MONITORING_UPTIME_HTTP_RETRIES', 3),

    'uptime_http_retry_delay_ms' => (int) env('SITE_MONITORING_UPTIME_HTTP_RETRY_DELAY_MS', 400),

    /**
     * Max bytes of response body stored on monitoring_logs.meta (GET only; HEAD skips body).
     */
    'http_log_body_max_bytes' => (int) env('SITE_MONITORING_HTTP_LOG_BODY_MAX_BYTES', 4096),

    /** Max response header lines stored in meta (name => value). */
    'http_log_header_max_lines' => (int) env('SITE_MONITORING_HTTP_LOG_HEADER_MAX_LINES', 20),

    /**
     * TLS handshake retries (transient network / handshake failures).
     */
    'ssl_connect_retries' => (int) env('SITE_MONITORING_SSL_CONNECT_RETRIES', 3),

    'ssl_connect_retry_delay_ms' => (int) env('SITE_MONITORING_SSL_CONNECT_RETRY_DELAY_MS', 500),

    /**
     * TCP connect probe timeout in milliseconds (used by tcp/ping check types).
     */
    'tcp_timeout_ms' => (int) env('SITE_MONITORING_TCP_TIMEOUT_MS', 5000),

    /**
     * Queue job retries when handle() throws (DB, lock, etc.). Probe failures do not throw.
     * Backoff list in seconds, comma-separated.
     */
    'run_job_tries' => (int) env('SITE_MONITORING_RUN_JOB_TRIES', 3),

    'run_job_backoff_seconds' => env('SITE_MONITORING_RUN_JOB_BACKOFF', '15,60,180'),

    'dispatch_job_tries' => (int) env('SITE_MONITORING_DISPATCH_JOB_TRIES', 3),

    'dispatch_job_backoff_seconds' => env('SITE_MONITORING_DISPATCH_JOB_BACKOFF', '30,120'),
];
