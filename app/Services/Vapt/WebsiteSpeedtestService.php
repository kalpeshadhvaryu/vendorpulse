<?php

namespace App\Services\Vapt;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class WebsiteSpeedtestService
{
    private const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) '
        .'Chrome/125.0.0.0 Safari/537.36';

    /**
     * @return array{
     *   target_url: string,
     *   tested_at: string,
    *   checked_from: string,
     *   final_url: string|null,
     *   status_code: int|null,
     *   success: bool,
     *   error: string|null,
     *   timeout_seconds: int,
     *   metrics: array{
     *     total_time_ms: float|null,
     *     ttfb_ms: float|null,
     *     dns_lookup_ms: float|null,
     *     tcp_connect_ms: float|null,
     *     tls_handshake_ms: float|null,
     *     redirect_time_ms: float|null,
     *     download_speed_kbps: float|null,
     *     downloaded_bytes: int|null
     *   }
     * }
     */
    public function run(string $targetUrl, int $timeoutSeconds = 20): array
    {
        $normalizedTargetUrl = $this->normalizeTargetUrl($targetUrl);
        $checkedFrom = $this->resolveCheckedFrom();

        $handlerStats = [];
        $statusCode = null;
        $effectiveUri = null;
        $error = null;

        $client = new Client([
            'timeout' => $timeoutSeconds,
            'connect_timeout' => min(8, $timeoutSeconds),
            'http_errors' => false,
            'allow_redirects' => true,
            'verify' => true,
        ]);

        try {
            $response = $client->request('GET', $normalizedTargetUrl, [
                'headers' => [
                    'User-Agent' => self::BROWSER_USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
                'on_stats' => static function ($stats) use (&$handlerStats, &$effectiveUri): void {
                    $handlerStats = $stats->getHandlerStats();
                    $effectiveUri = (string) $stats->getEffectiveUri();
                },
            ]);

            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $metrics = $this->buildMetrics($handlerStats);

        return [
            'target_url' => $normalizedTargetUrl,
            'tested_at' => now()->toIso8601String(),
            'checked_from' => $checkedFrom,
            'final_url' => $effectiveUri,
            'status_code' => $statusCode,
            'success' => $error === null && $statusCode !== null,
            'error' => $error,
            'timeout_seconds' => $timeoutSeconds,
            'metrics' => $metrics,
        ];
    }

    private function resolveCheckedFrom(): string
    {
        $host = gethostname() ?: php_uname('n');
        $appUrlHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($appUrlHost !== '' && $host !== $appUrlHost) {
            return $host.' (app: '.$appUrlHost.')';
        }

        return (string) $host;
    }

    private function normalizeTargetUrl(string $targetUrl): string
    {
        $trimmed = trim($targetUrl);

        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            $trimmed = 'https://'.$trimmed;
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $handlerStats
     * @return array{
     *   total_time_ms: float|null,
     *   ttfb_ms: float|null,
     *   dns_lookup_ms: float|null,
     *   tcp_connect_ms: float|null,
     *   tls_handshake_ms: float|null,
     *   redirect_time_ms: float|null,
     *   download_speed_kbps: float|null,
     *   downloaded_bytes: int|null
     * }
     */
    private function buildMetrics(array $handlerStats): array
    {
        $num = static fn (string $key): ?float => isset($handlerStats[$key]) && is_numeric($handlerStats[$key])
            ? (float) $handlerStats[$key]
            : null;

        $ms = static fn (?float $seconds): ?float => $seconds !== null ? round($seconds * 1000, 2) : null;

        $nameLookup = $num('namelookup_time');
        $connect = $num('connect_time');
        $appConnect = $num('appconnect_time');
        $startTransfer = $num('starttransfer_time');
        $totalTime = $num('total_time');
        $redirectTime = $num('redirect_time');
        $sizeDownload = $num('size_download');
        $speedDownload = $num('speed_download');

        $tcpConnect = null;
        if ($connect !== null && $nameLookup !== null) {
            $tcpConnect = max(0.0, $connect - $nameLookup);
        }

        $tlsHandshake = null;
        if ($appConnect !== null && $connect !== null && $appConnect > 0 && $appConnect >= $connect) {
            $tlsHandshake = max(0.0, $appConnect - $connect);
        }

        return [
            'total_time_ms' => $ms($totalTime),
            'ttfb_ms' => $ms($startTransfer),
            'dns_lookup_ms' => $ms($nameLookup),
            'tcp_connect_ms' => $ms($tcpConnect),
            'tls_handshake_ms' => $ms($tlsHandshake),
            'redirect_time_ms' => $ms($redirectTime),
            'download_speed_kbps' => $speedDownload !== null ? round(($speedDownload * 8) / 1000, 2) : null,
            'downloaded_bytes' => $sizeDownload !== null ? (int) round($sizeDownload) : null,
        ];
    }
}
