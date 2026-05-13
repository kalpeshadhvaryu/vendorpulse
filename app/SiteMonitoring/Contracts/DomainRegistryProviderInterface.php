<?php

namespace App\SiteMonitoring\Contracts;

/**
 * Resolve domain registration expiry (RDAP/WHOIS provider). Implementations may call external APIs.
 */
interface DomainRegistryProviderInterface
{
    /**
     * @return array{expires_at: ?\DateTimeImmutable, registrar: ?string}|null Null when unknown / not configured.
     */
    public function lookupExpiry(string $registerableDomain): ?array;
}
