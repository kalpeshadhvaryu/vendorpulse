<?php

namespace App\SiteMonitoring\Enums;

enum MonitoringLogStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Error = 'error';
}
