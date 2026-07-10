<?php

use App\Http\Controllers\Public\PublicToolsController;
use Illuminate\Support\Facades\Route;

$dashboardBaseUrl = rtrim((string) config('vendorpulse.web_dashboard_url'), '/');

Route::get('/', [PublicToolsController::class, 'home']);
Route::get('/privacy', [PublicToolsController::class, 'privacy']);

Route::get('/web_dashboard/{path?}', function (?string $path = null) use ($dashboardBaseUrl) {
    $suffix = $path !== null && $path !== '' ? '/'.ltrim($path, '/') : '';
    $query = request()->getQueryString();

    return redirect()->away($dashboardBaseUrl.'/web_dashboard'.$suffix.($query ? '?'.$query : ''));
})->where('path', '.*');
