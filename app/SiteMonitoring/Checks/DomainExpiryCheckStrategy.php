<?php

namespace App\SiteMonitoring\Checks;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\Contracts\CheckStrategyInterface;
use App\SiteMonitoring\Contracts\DomainRegistryProviderInterface;
use App\SiteMonitoring\DTO\ProbeResult;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use App\SiteMonitoring\Support\MonitoringEndpoint;
use DateTimeImmutable;
use DateTimeZone;

class DomainExpiryCheckStrategy implements CheckStrategyInterface
{
    public function __construct(
        protected DomainRegistryProviderInterface $domains,
    ) {}

    public function execute(MonitoringCheck $check): ProbeResult
    {
        $endpoint = $check->endpoint;
        if ($endpoint === null || $endpoint === '') {
            return ProbeResult::skipped('Missing endpoint for domain check.');
        }

        $domain = MonitoringEndpoint::registerableDomainFromEndpoint($endpoint);
        if ($domain === '') {
            return ProbeResult::error('Could not derive domain from endpoint.');
        }

        $lookup = $this->domains->lookupExpiry($domain);
        if ($lookup === null || $lookup['expires_at'] === null) {
            return new ProbeResult(
                MonitoringLogStatus::Skipped,
                'Domain registry provider returned no expiry (configure RDAP/WHOIS integration).',
                null,
                null,
                ['domain' => $domain]
            );
        }

        $expiresAt = $lookup['expires_at'];
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $daysRemaining = (int) floor(($expiresAt->getTimestamp() - $now->getTimestamp()) / 86400);

        $warningDays = (int) config('site-monitoring.domain_warning_days', 30);

        $meta = [
            'domain' => $domain,
            'domain_expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
            'domain_days_remaining' => $daysRemaining,
            'registrar' => $lookup['registrar'],
        ];

        if ($daysRemaining < 0) {
            return new ProbeResult(
                MonitoringLogStatus::Failed,
                'Domain registration expired.',
                null,
                null,
                $meta
            );
        }

        if ($daysRemaining <= $warningDays) {
            return new ProbeResult(
                MonitoringLogStatus::Degraded,
                'Domain expires in '.$daysRemaining.' day(s).',
                null,
                null,
                $meta
            );
        }

        return new ProbeResult(
            MonitoringLogStatus::Ok,
            'Domain registration is healthy.',
            null,
            null,
            $meta
        );
    }
}
