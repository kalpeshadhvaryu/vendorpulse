<?php

namespace App\SiteMonitoring\Services;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\Checks\DomainExpiryCheckStrategy;
use App\SiteMonitoring\Checks\SslCertificateCheckStrategy;
use App\SiteMonitoring\Checks\UnsupportedCheckStrategy;
use App\SiteMonitoring\Checks\UptimeHttpCheckStrategy;
use App\SiteMonitoring\Contracts\CheckStrategyInterface;

class MonitoringStrategyRegistry
{
    public function __construct(
        protected UptimeHttpCheckStrategy $uptime,
        protected SslCertificateCheckStrategy $ssl,
        protected DomainExpiryCheckStrategy $domain,
        protected UnsupportedCheckStrategy $unsupported,
    ) {}

    public function resolve(MonitoringCheck $check): CheckStrategyInterface
    {
        return match (strtolower((string) $check->type)) {
            'uptime', 'http', 'https' => $this->uptime,
            'ssl', 'tls' => $this->ssl,
            'domain', 'whois' => $this->domain,
            default => $this->unsupported,
        };
    }
}
