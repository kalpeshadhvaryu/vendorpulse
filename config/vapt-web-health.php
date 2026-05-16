<?php

return [
    // Notify when total load time is at or above this threshold (milliseconds).
    'speedtest_alert_threshold_ms' => (int) env('VAPT_SPEEDTEST_ALERT_THRESHOLD_MS', 3000),

    // Consider only runs within this recent window (minutes).
    'speedtest_alert_window_minutes' => (int) env('VAPT_SPEEDTEST_ALERT_WINDOW_MINUTES', 15),

    // Throttle repeated alerts per organization+target URL.
    'speedtest_alert_cooldown_minutes' => (int) env('VAPT_SPEEDTEST_ALERT_COOLDOWN_MINUTES', 60),
];
