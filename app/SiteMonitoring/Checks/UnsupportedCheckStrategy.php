<?php

namespace App\SiteMonitoring\Checks;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\Contracts\CheckStrategyInterface;
use App\SiteMonitoring\DTO\ProbeResult;

/**
 * Legacy / future check types (tcp, ping, dns, custom) until dedicated strategies exist.
 */
class UnsupportedCheckStrategy implements CheckStrategyInterface
{
    public function execute(MonitoringCheck $check): ProbeResult
    {
        return ProbeResult::skipped('Check type "'.$check->type.'" has no runner yet.');
    }
}
