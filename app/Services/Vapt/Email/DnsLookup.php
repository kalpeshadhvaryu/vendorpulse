<?php

namespace App\Services\Vapt\Email;

use Illuminate\Support\Facades\Http;

class DnsLookup
{
    /**
     * @return array<int, string>
     */
    public function txt(string $name): array
    {
        $values = [];

        foreach ((array) @dns_get_record($this->fqdn($name), DNS_TXT) as $record) {
            if (! is_array($record)) {
                continue;
            }

            $entries = $record['entries'] ?? null;
            $value = is_array($entries) ? implode('', $entries) : (string) ($record['txt'] ?? '');

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return array<int, string>
     */
    public function a(string $name): array
    {
        $ips = [];

        foreach ((array) @dns_get_record($this->fqdn($name), DNS_A) as $record) {
            if (is_array($record) && isset($record['ip'])) {
                $ips[] = (string) $record['ip'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @return array<int, array{host:string, priority:int}>
     */
    public function mx(string $name): array
    {
        $hosts = [];

        foreach ((array) @dns_get_record($this->fqdn($name), DNS_MX) as $record) {
            if (is_array($record) && isset($record['target'])) {
                $hosts[] = [
                    'host' => rtrim(strtolower((string) $record['target']), '.'),
                    'priority' => (int) ($record['pri'] ?? 0),
                ];
            }
        }

        usort($hosts, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $hosts;
    }

    /**
     * @return array<int, string>
     */
    public function ptr(string $ip): array
    {
        $reverse = $this->reverseName($ip);
        if ($reverse === null) {
            return [];
        }

        $hosts = [];

        foreach ((array) @dns_get_record($reverse.'.', DNS_PTR) as $record) {
            if (is_array($record) && isset($record['target'])) {
                $hosts[] = rtrim(strtolower((string) $record['target']), '.');
            }
        }

        if ($hosts === []) {
            $hosts = $this->ptrOverHttps($reverse);
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return array<int, string>
     */
    public function aaaa(string $name): array
    {
        $ips = [];

        foreach ((array) @dns_get_record($this->fqdn($name), DNS_AAAA) as $record) {
            if (is_array($record) && isset($record['ipv6'])) {
                $ips[] = (string) inet_ntop((string) inet_pton((string) $record['ipv6']));
            }
        }

        return array_values(array_unique($ips));
    }

    private function reverseName(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_reverse(explode('.', $ip))).'.in-addr.arpa';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hex = bin2hex((string) inet_pton($ip));

            return implode('.', array_reverse(str_split($hex))).'.ip6.arpa';
        }

        return null;
    }

    /**
     * Some container resolvers (e.g. Docker Desktop) do not answer PTR queries for external IPs.
     *
     * @return array<int, string>
     */
    private function ptrOverHttps(string $reverse): array
    {
        try {
            $response = Http::timeout(4)->get('https://dns.google/resolve', ['name' => $reverse, 'type' => 'PTR']);
            if (! $response->ok()) {
                return [];
            }

            $hosts = [];
            foreach ((array) ($response->json('Answer') ?? []) as $answer) {
                if (is_array($answer) && (int) ($answer['type'] ?? 0) === 12 && isset($answer['data'])) {
                    $hosts[] = rtrim(strtolower((string) $answer['data']), '.');
                }
            }

            return $hosts;
        } catch (\Throwable) {
            return [];
        }
    }

    private function fqdn(string $name): string
    {
        return rtrim($name, '.').'.';
    }
}
