<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $webDashboardUrl = rtrim((string) env('WEB_DASHBOARD_URL', ''), '/');

    if ($webDashboardUrl !== '') {
        return redirect()->away($webDashboardUrl.'/login');
    }

    return view('welcome');
});
