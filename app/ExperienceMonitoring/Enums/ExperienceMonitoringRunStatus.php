<?php

namespace App\ExperienceMonitoring\Enums;

enum ExperienceMonitoringRunStatus: string
{
    case Ok = 'ok';
    case FailedLogin = 'failed_login';
    case SlowDashboard = 'slow_dashboard';
    case Timeout = 'timeout';
    case JsError = 'js_error';
    case Error = 'error';
}
