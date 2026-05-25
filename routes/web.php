<?php

use Illuminate\Support\Facades\Route;

// Laravel does not serve Next.js pages. Redirect browser traffic to the Next.js base URL.
// Local: Next on :3000. Production: set WEB_DASHBOARD_URL (or APP_URL) to the public site origin.
$dashboardBaseUrl = rtrim((string) env('WEB_DASHBOARD_URL', match (env('APP_ENV', 'production')) {
    'local' => 'http://127.0.0.1:3000',
    default => (string) env('APP_URL', 'http://127.0.0.1:3000'),
}), '/');

Route::get('/', function () use ($dashboardBaseUrl) {
    return redirect()->away($dashboardBaseUrl.'/web_dashboard');
});

Route::get('/web_dashboard/{path?}', function (?string $path = null) use ($dashboardBaseUrl) {

    $suffix = $path !== null && $path !== '' ? '/'.ltrim($path, '/') : '';
    $query = request()->getQueryString();

    return redirect()->away($dashboardBaseUrl.'/web_dashboard'.$suffix.($query ? '?'.$query : ''));
})->where('path', '.*');
