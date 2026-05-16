<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DashboardAnalyticsController;
use App\Http\Controllers\Api\V1\EmailMailboxController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MonitoringCheckController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrganizationManagementController;
use App\Http\Controllers\Api\V1\OrganizationSmtpSettingsController;
use App\Http\Controllers\Api\V1\VendorController;
use App\Http\Controllers\Api\V1\VendorEmailController;
use App\Http\Controllers\Api\V1\VaptWebHealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('organizations/users', [OrganizationManagementController::class, 'users']);
        Route::get('organizations', [OrganizationManagementController::class, 'index']);
        Route::post('organizations', [OrganizationManagementController::class, 'store']);
        Route::delete('organizations/{organization}', [OrganizationManagementController::class, 'destroy']);
        Route::delete('organizations/{organization}/force', [OrganizationManagementController::class, 'forceDestroy']);
        Route::post('organizations/{organization}/members', [OrganizationManagementController::class, 'attachMember']);
        Route::post('organizations/{organization}/users', [OrganizationManagementController::class, 'createUser']);

        Route::middleware('organization.context')->group(function (): void {
            Route::get('dashboard/trends', [DashboardAnalyticsController::class, 'trends']);
            Route::post('vendors/{id}/restore', [VendorController::class, 'restore']);
            Route::apiResource('vendors', VendorController::class);
            Route::apiResource('vendor-emails', VendorEmailController::class);
            Route::apiResource('invoices', InvoiceController::class);
            Route::get('monitoring-checks/{monitoring_check}/logs', [MonitoringCheckController::class, 'logs']);
            Route::get('monitoring-checks/{monitoring_check}/log-summary', [MonitoringCheckController::class, 'logSummary']);
            Route::get('monitoring-checks/{monitoring_check}/server-analytics', [MonitoringCheckController::class, 'serverAnalytics']);
            Route::post('monitoring-checks/{monitoring_check}/run', [MonitoringCheckController::class, 'run']);
            Route::apiResource('monitoring-checks', MonitoringCheckController::class);

            Route::apiResource('email-mailboxes', EmailMailboxController::class);

            Route::get('organization/smtp-settings', [OrganizationSmtpSettingsController::class, 'show']);
            Route::put('organization/smtp-settings', [OrganizationSmtpSettingsController::class, 'update']);
            Route::post('organization/smtp-settings/test', [OrganizationSmtpSettingsController::class, 'sendTestEmail']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

            Route::post('vapt-web-health/url-checker', [VaptWebHealthController::class, 'urlChecker']);
        });
    });
});
