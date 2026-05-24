<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DashboardAnalyticsController;
use App\Http\Controllers\Api\V1\DomainSocialAccountController;
use App\Http\Controllers\Api\V1\EmailLogController;
use App\Http\Controllers\Api\V1\EmailMailboxController;
use App\Http\Controllers\Api\V1\ExperienceMonitoringController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MarketingSeoController;
use App\Http\Controllers\Api\V1\MonitoringCheckController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrganizationManagementController;
use App\Http\Controllers\Api\V1\OrganizationSmtpSettingsController;
use App\Http\Controllers\Api\V1\SystemSettingsController;
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
        Route::get('organizations/users/{user}', [OrganizationManagementController::class, 'showUser']);
        Route::patch('organizations/users/{user}', [OrganizationManagementController::class, 'updateUser']);
        Route::delete('organizations/users/{user}', [OrganizationManagementController::class, 'destroyUser']);
        Route::post('organizations/users/{user}/restore', [OrganizationManagementController::class, 'restoreUser']);
        Route::patch('organizations/users/{user}/global-access', [OrganizationManagementController::class, 'updateUserGlobalAccess']);
        Route::get('organizations', [OrganizationManagementController::class, 'index']);
        Route::post('organizations', [OrganizationManagementController::class, 'store']);
        Route::get('organizations/{organization}', [OrganizationManagementController::class, 'show']);
        Route::patch('organizations/{organization}', [OrganizationManagementController::class, 'update']);
        Route::delete('organizations/{organization}', [OrganizationManagementController::class, 'destroy']);
        Route::post('organizations/{organization}/restore', [OrganizationManagementController::class, 'restore']);
        Route::delete('organizations/{organization}/force', [OrganizationManagementController::class, 'forceDestroy']);
        Route::post('organizations/{organization}/members', [OrganizationManagementController::class, 'attachMember']);
        Route::patch('organizations/{organization}/members/{user}', [OrganizationManagementController::class, 'updateMember']);
        Route::delete('organizations/{organization}/members/{user}', [OrganizationManagementController::class, 'detachMember']);
        Route::post('organizations/{organization}/users', [OrganizationManagementController::class, 'createUser']);
        Route::get('settings/management-email-notifications', [SystemSettingsController::class, 'showManagementEmailNotifications']);
        Route::put('settings/management-email-notifications', [SystemSettingsController::class, 'updateManagementEmailNotifications']);
        Route::get('settings/startup-health', [SystemSettingsController::class, 'startupHealth']);
        Route::get('settings/main-smtp', [SystemSettingsController::class, 'showMainSmtpSettings']);
        Route::put('settings/main-smtp', [SystemSettingsController::class, 'updateMainSmtpSettings']);
        Route::post('settings/main-smtp/test', [SystemSettingsController::class, 'sendMainSmtpTestEmail']);

        Route::middleware('organization.context')->group(function (): void {
            Route::get('dashboard/trends', [DashboardAnalyticsController::class, 'trends']);
            Route::get('dashboard/monitoring-create-fallbacks', [DashboardAnalyticsController::class, 'monitoringCreateFallbacks']);
            Route::post('vendors/{id}/restore', [VendorController::class, 'restore']);
            Route::apiResource('vendors', VendorController::class);
            Route::apiResource('vendor-emails', VendorEmailController::class);
            Route::apiResource('invoices', InvoiceController::class);
            Route::get('monitoring-checks/{monitoring_check}/logs', [MonitoringCheckController::class, 'logs']);
            Route::get('monitoring-checks/{monitoring_check}/log-summary', [MonitoringCheckController::class, 'logSummary']);
            Route::get('monitoring-checks/{monitoring_check}/server-analytics', [MonitoringCheckController::class, 'serverAnalytics']);
            Route::post('monitoring-checks/{monitoring_check}/run', [MonitoringCheckController::class, 'run']);
            Route::apiResource('monitoring-checks', MonitoringCheckController::class);

            Route::get('experience-monitoring-tests/{experience_monitoring_test}/runs', [ExperienceMonitoringController::class, 'runs']);
            Route::get('experience-monitoring-tests/{experience_monitoring_test}/metrics', [ExperienceMonitoringController::class, 'metrics']);
            Route::get('experience-monitoring-tests/{experience_monitoring_test}/report', [ExperienceMonitoringController::class, 'report']);
            Route::get('experience-monitoring-tests/{experience_monitoring_test}/screenshots', [ExperienceMonitoringController::class, 'screenshots']);
            Route::post('experience-monitoring-tests/{experience_monitoring_test}/trigger', [ExperienceMonitoringController::class, 'trigger']);
            Route::get('experience-monitoring-screenshots/{experience_monitoring_screenshot}/file', [ExperienceMonitoringController::class, 'screenshotFile']);
            Route::apiResource('experience-monitoring-tests', ExperienceMonitoringController::class);

            Route::post('email-mailboxes/{email_mailbox}/test-connection', [EmailMailboxController::class, 'testConnection']);
            Route::apiResource('email-mailboxes', EmailMailboxController::class);
            Route::get('email-logs', [EmailLogController::class, 'index']);
            Route::get('email-logs/{email_log}', [EmailLogController::class, 'show']);

            Route::get('organization/smtp-settings', [OrganizationSmtpSettingsController::class, 'show']);
            Route::put('organization/smtp-settings', [OrganizationSmtpSettingsController::class, 'update']);
            Route::post('organization/smtp-settings/test', [OrganizationSmtpSettingsController::class, 'sendTestEmail']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

            Route::post('vapt-web-health/url-checker', [VaptWebHealthController::class, 'urlChecker']);
            Route::post('vapt-web-health/website-speedtest', [VaptWebHealthController::class, 'websiteSpeedtest']);
            Route::get('vapt-web-health/website-speedtest-runs', [VaptWebHealthController::class, 'websiteSpeedtestRuns']);
            Route::post('vapt-web-health/dns-check', [VaptWebHealthController::class, 'dnsCheck']);
            Route::post('vapt-web-health/port-checker', [VaptWebHealthController::class, 'portChecker']);
            Route::post('marketing-seo/on-page-audit', [MarketingSeoController::class, 'onPageAudit']);
            Route::get('social-accounts', [DomainSocialAccountController::class, 'index']);
            Route::post('social-accounts', [DomainSocialAccountController::class, 'store']);
            Route::patch('social-accounts/{domain_social_account}', [DomainSocialAccountController::class, 'update']);
            Route::delete('social-accounts/{domain_social_account}', [DomainSocialAccountController::class, 'destroy']);
        });
    });
});

Route::middleware(['auth:sanctum', 'organization.context'])
    ->get('social-accounts', [DomainSocialAccountController::class, 'index']);
Route::middleware(['auth:sanctum', 'organization.context'])
    ->post('social-accounts', [DomainSocialAccountController::class, 'store']);
Route::middleware(['auth:sanctum', 'organization.context'])
    ->patch('social-accounts/{domain_social_account}', [DomainSocialAccountController::class, 'update']);
Route::middleware(['auth:sanctum', 'organization.context'])
    ->delete('social-accounts/{domain_social_account}', [DomainSocialAccountController::class, 'destroy']);
