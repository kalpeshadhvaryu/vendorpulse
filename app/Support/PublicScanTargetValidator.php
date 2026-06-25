<?php

namespace App\Support;

class PublicScanTargetValidator
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
    ];

    public function normalizeHost(string $target): ?string
    {
        $value = strtolower(trim($target));
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);
        } else {
            $candidate = str_contains($value, '/') || str_contains($value, '?') || str_contains($value, '#')
                ? 'https://'.$value
                : $value;

            $host = str_contains($candidate, '://')
                ? parse_url($candidate, PHP_URL_HOST)
                : $candidate;
        }

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $host = trim($host, '.');

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return null;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host) ? $host : null;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        return $host;
    }

    public function isAllowed(string $target): bool
    {
        return $this->normalizeHost($target) !== null;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
