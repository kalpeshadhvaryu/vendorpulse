<?php

namespace App\Services\Public;

use App\SiteMonitoring\Support\MonitoringEndpoint;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Throwable;

class PublicWhoisService
{
    /**
     * Public RDAP (WHOIS-like) lookup for a domain.
     *
     * @return array{
     *   domain: string,
     *   found: bool,
     *   expires_at: string|null,
     *   days_remaining: int|null,
     *   created_at: string|null,
     *   updated_at: string|null,
     *   registrar: string|null,
     *   statuses: list<string>,
     *   nameservers: list<string>,
     *   source: string
     * }
     */
    public function lookup(string $target): array
    {
        $domain = MonitoringEndpoint::registerableDomainFromEndpoint($target);
        if ($domain === '') {
            $domain = mb_strtolower(trim($target));
        }

        $empty = [
            'domain' => $domain,
            'found' => false,
            'expires_at' => null,
            'days_remaining' => null,
            'created_at' => null,
            'updated_at' => null,
            'registrar' => null,
            'statuses' => [],
            'nameservers' => [],
            'source' => 'rdap',
        ];

        if ($domain === '' || ! str_contains($domain, '.')) {
            return $empty;
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
                        'User-Agent' => 'VendorPulse-PublicWhois/1.0',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    break;
                }
            } catch (Throwable) {
                $response = null;
            }

            if ($attempt < $maxAttempts) {
                usleep(300_000);
            }
        }

        if ($response === null || ! $response->successful()) {
            return $empty;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return $empty;
        }

        $expiresAt = $this->extractEventDate($data, 'expir');
        $createdAt = $this->extractEventDate($data, 'registration')
            ?? $this->extractEventDate($data, 'registered');
        $updatedAt = $this->extractEventDate($data, 'last changed')
            ?? $this->extractEventDate($data, 'last update of rdap database')
            ?? $this->extractEventDate($data, 'last update');

        $daysRemaining = null;
        if ($expiresAt !== null) {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $daysRemaining = (int) floor(($expiresAt->getTimestamp() - $now->getTimestamp()) / 86400);
        }

        return [
            'domain' => $domain,
            'found' => true,
            'expires_at' => $expiresAt?->format(DateTimeImmutable::ATOM),
            'days_remaining' => $daysRemaining,
            'created_at' => $createdAt?->format(DateTimeImmutable::ATOM),
            'updated_at' => $updatedAt?->format(DateTimeImmutable::ATOM),
            'registrar' => $this->extractRegistrar($data),
            'statuses' => $this->extractStatuses($data),
            'nameservers' => $this->extractNameservers($data),
            'source' => 'rdap',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractEventDate(array $data, string $actionNeedle): ?DateTimeImmutable
    {
        $needle = mb_strtolower($actionNeedle);
        $candidates = [];

        foreach ($data['events'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $action = mb_strtolower((string) ($event['eventAction'] ?? ''));
            if (! str_contains($action, $needle)) {
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

        return end($candidates) ?: null;
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

            $isRegistrar = false;
            foreach ($roles as $role) {
                if (is_string($role) && mb_strtolower($role) === 'registrar') {
                    $isRegistrar = true;
                    break;
                }
            }

            if (! $isRegistrar) {
                continue;
            }

            $vcardArray = $entity['vcardArray'] ?? null;
            if (is_array($vcardArray) && isset($vcardArray[1]) && is_array($vcardArray[1])) {
                foreach ($vcardArray[1] as $prop) {
                    if (! is_array($prop) || count($prop) < 4) {
                        continue;
                    }

                    if (($prop[0] ?? '') === 'fn' && is_string($prop[3] ?? null) && $prop[3] !== '') {
                        return $prop[3];
                    }
                }
            }

            if (isset($entity['handle']) && is_string($entity['handle']) && $entity['handle'] !== '') {
                return $entity['handle'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function extractStatuses(array $data): array
    {
        $statuses = [];
        foreach ($data['status'] ?? [] as $status) {
            if (is_string($status) && $status !== '') {
                $statuses[] = $status;
            }
        }

        return array_values(array_unique($statuses));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function extractNameservers(array $data): array
    {
        $names = [];
        foreach ($data['nameservers'] ?? [] as $ns) {
            if (! is_array($ns)) {
                continue;
            }
            $ldh = $ns['ldhName'] ?? $ns['unicodeName'] ?? null;
            if (is_string($ldh) && $ldh !== '') {
                $names[] = mb_strtolower(rtrim($ldh, '.'));
            }
        }

        return array_values(array_unique($names));
    }
}
