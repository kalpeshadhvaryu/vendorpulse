<?php

namespace App\SiteMonitoring\Contracts;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\DTO\ProbeResult;

interface CheckStrategyInterface
{
    public function execute(MonitoringCheck $check): ProbeResult;
}
