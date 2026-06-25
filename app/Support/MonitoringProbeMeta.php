<?php

namespace App\Support;

class MonitoringProbeMeta
{
    /**
     * @param  array<string, mixed>|null  $meta
     * @return array{
     *   domain: ?string,
     *   domain_expires_at: ?string,
     *   domain_days_remaining: ?int,
     *   domain_registrar: ?string,
     *   ssl_expires_at: ?string,
     *   ssl_days_remaining: ?int
     * }
     */
    public static function extract(?array $meta): array
    {
        if ($meta === null || $meta === []) {
            return [
                'domain' => null,
                'domain_expires_at' => null,
                'domain_days_remaining' => null,
                'domain_registrar' => null,
                'ssl_expires_at' => null,
                'ssl_days_remaining' => null,
            ];
        }

        $domainDays = $meta['domain_days_remaining'] ?? null;
        $sslDays = $meta['ssl_days_remaining'] ?? $meta['certificate_days_remaining'] ?? null;

        return [
            'domain' => self::stringOrNull($meta['domain'] ?? null),
            'domain_expires_at' => self::stringOrNull($meta['domain_expires_at'] ?? null),
            'domain_days_remaining' => is_numeric($domainDays) ? (int) $domainDays : null,
            'domain_registrar' => self::stringOrNull($meta['registrar'] ?? null),
            'ssl_expires_at' => self::stringOrNull($meta['ssl_expires_at'] ?? $meta['certificate_expires_at'] ?? null),
            'ssl_days_remaining' => is_numeric($sslDays) ? (int) $sslDays : null,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
