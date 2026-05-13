<?php

namespace App\SiteMonitoring;

use App\SiteMonitoring\Contracts\DomainRegistryProviderInterface;
use App\SiteMonitoring\Events\SiteMonitoringDomainExpiringSoon;
use App\SiteMonitoring\Events\SiteMonitoringSslExpiringSoon;
use App\SiteMonitoring\Events\SiteMonitoringUptimeCheckFailed;
use App\SiteMonitoring\Events\SiteMonitoringUptimeCheckRecovered;
use App\SiteMonitoring\Infrastructure\NullDomainRegistryProvider;
use App\SiteMonitoring\Infrastructure\RdapDomainRegistryProvider;
use App\SiteMonitoring\Listeners\NotifySiteMonitoringDomainExpiringSoon;
use App\SiteMonitoring\Listeners\NotifySiteMonitoringSslExpiringSoon;
use App\SiteMonitoring\Listeners\NotifySiteMonitoringUptimeCheckFailed;
use App\SiteMonitoring\Listeners\NotifySiteMonitoringUptimeCheckRecovered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SiteMonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DomainRegistryProviderInterface::class, function ($app) {
            return match ((string) config('site-monitoring.domain_provider', 'rdap')) {
                'rdap' => $app->make(RdapDomainRegistryProvider::class),
                default => new NullDomainRegistryProvider,
            };
        });
    }

    public function boot(): void
    {
        Event::listen(SiteMonitoringUptimeCheckFailed::class, NotifySiteMonitoringUptimeCheckFailed::class);
        Event::listen(SiteMonitoringUptimeCheckRecovered::class, NotifySiteMonitoringUptimeCheckRecovered::class);
        Event::listen(SiteMonitoringSslExpiringSoon::class, NotifySiteMonitoringSslExpiringSoon::class);
        Event::listen(SiteMonitoringDomainExpiringSoon::class, NotifySiteMonitoringDomainExpiringSoon::class);
    }
}
