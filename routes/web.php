<?php

use Illuminate\Support\Facades\Route;

// In local artisan mode, Laravel does not serve Next.js pages directly.
// Redirect `/web_dashboard` requests to the Next.js server.
$dashboardBaseUrl = rtrim((string) env('WEB_DASHBOARD_URL', 'http://127.0.0.1:3000'), '/');

Route::get('/', function () use ($dashboardBaseUrl) {
    return redirect()->away($dashboardBaseUrl.'/web_dashboard');
});

Route::get('/web_dashboard/{path?}', function (?string $path = null) use ($dashboardBaseUrl) {

    $suffix = $path !== null && $path !== '' ? '/'.ltrim($path, '/') : '';
    $query = request()->getQueryString();

    return redirect()->away($dashboardBaseUrl.'/web_dashboard'.$suffix.($query ? '?'.$query : ''));
})->where('path', '.*');
