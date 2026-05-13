<?php

namespace App\SiteMonitoring\Support;

final class MonitoringEndpoint
{
    public static function toHttpUrl(string $endpoint, string $type): string
    {
        $e = trim($endpoint);
        if (preg_match('#^https?://#i', $e)) {
            return $e;
        }

        $scheme = strtolower($type) === 'http' ? 'http' : 'https';

        return $scheme.'://'.ltrim($e, '/');
    }

    public static function registerableDomainFromEndpoint(string $endpoint): string
    {
        $e = trim($endpoint);
        if (preg_match('#^https?://#i', $e)) {
            $host = parse_url($e, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return strtolower($host);
            }
        }

        return strtolower(preg_replace('#^/+|/+$#', '', $e) ?? $e);
    }
}
