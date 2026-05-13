<?php

namespace App\SiteMonitoring\Infrastructure;

use App\SiteMonitoring\Contracts\DomainRegistryProviderInterface;

/**
 * Placeholder until an RDAP/WHOIS client is wired.
 */
class NullDomainRegistryProvider implements DomainRegistryProviderInterface
{
    public function lookupExpiry(string $registerableDomain): ?array
    {
        return null;
    }
}
