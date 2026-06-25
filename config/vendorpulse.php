<?php

return [
    'web_dashboard_url' => rtrim((string) env('WEB_DASHBOARD_URL', match (env('APP_ENV', 'production')) {
        'local' => 'http://127.0.0.1:3000',
        default => (string) env('APP_URL', 'http://127.0.0.1:3000'),
    }), '/'),
];
