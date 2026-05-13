<?php

namespace App\SiteMonitoring\Events;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteMonitoringDomainExpiringSoon
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public MonitoringCheck $check,
        public MonitoringLog $log,
    ) {}
}
