<?php

namespace App\SiteMonitoring\Infrastructure;

use App\SiteMonitoring\Contracts\DomainRegistryProviderInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves domain expiry via public RDAP (rdap.org bootstrap redirect to TLD registry).
 */
class RdapDomainRegistryProvider implements DomainRegistryProviderInterface
{
    public function lookupExpiry(string $registerableDomain): ?array
    {
        $domain = mb_strtolower(trim($registerableDomain));
        if ($domain === '' || ! str_contains($domain, '.')) {
            return null;
        }

        $timeout = max(5, (int) config('site-monitoring.rdap_timeout_seconds', 25));
        $maxAttempts = max(1, (int) config('site-monitoring.rdap_retries', 2));
        $url = 'https://rdap.org/domain/'.$domain;

        $response = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Accept' => 'application/rdap+json, application/json, */*;q=0.1',
                        'User-Agent' => 'VendorPulse-SiteMonitoring/1.0',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    break;
                }
            } catch (Throwable) {
                $response = null;
            }

            if ($response !== null && $response->successful()) {
                break;
            }

            if ($attempt < $maxAttempts) {
                usleep(300_000);
            }
        }

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        $expiresAt = $this->extractExpiration($data);
        if ($expiresAt === null) {
            return null;
        }

        return [
            'expires_at' => $expiresAt,
            'registrar' => $this->extractRegistrar($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractExpiration(array $data): ?DateTimeImmutable
    {
        $candidates = [];

        foreach ($data['events'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $action = mb_strtolower((string) ($event['eventAction'] ?? ''));
            if (! str_contains($action, 'expir')) {
                continue;
            }

            $raw = $event['eventDate'] ?? null;
            if (! is_string($raw) || $raw === '') {
                continue;
            }

            try {
                $candidates[] = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
            } catch (Throwable) {
                continue;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);

        return end($candidates);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractRegistrar(array $data): ?string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $roles = $entity['roles'] ?? [];
            if (! is_array($roles)) {
                continue;
            }

            foreach ($roles as $role) {
                if (is_string($role) && mb_strtolower($role) === 'registrar') {
                    foreach ($entity['vcardArray'] ?? [] as $vcard) {
                        if (! is_array($vcard) || ($vcard[0] ?? '') !== 'vcard') {
                            continue;
                        }

                        foreach ($vcard[1] ?? [] as $prop) {
                            if (! is_array($prop) || count($prop) < 4) {
                                continue;
                            }

                            if (($prop[0] ?? '') === 'fn' && is_string($prop[3] ?? null)) {
                                return $prop[3];
                            }
                        }
                    }

                    return $entity['handle'] ?? null;
                }
            }
        }

        return null;
    }
}
