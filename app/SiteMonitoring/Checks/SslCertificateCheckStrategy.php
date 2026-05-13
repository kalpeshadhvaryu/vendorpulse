<?php

namespace App\SiteMonitoring\Checks;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\Contracts\CheckStrategyInterface;
use App\SiteMonitoring\DTO\ProbeResult;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use DateTimeImmutable;
use DateTimeZone;

class SslCertificateCheckStrategy implements CheckStrategyInterface
{
    public function execute(MonitoringCheck $check): ProbeResult
    {
        $endpoint = $check->endpoint;
        if ($endpoint === null || $endpoint === '') {
            return ProbeResult::skipped('Missing endpoint for SSL check.');
        }

        $configuration = $check->configuration ?? [];
        $url = trim($endpoint);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return ProbeResult::error('Could not derive host from endpoint.');
        }

        $port = (int) ($configuration['port'] ?? 443);
        $timeout = (int) ($configuration['timeout_seconds'] ?? 15);
        $timeout = max(1, min($timeout, 120));
        $verifyPeer = (bool) ($configuration['verify_ssl'] ?? true);

        $maxAttempts = max(1, (int) ($configuration['connect_retries'] ?? config('site-monitoring.ssl_connect_retries', 3)));
        $delayMs = max(0, (int) ($configuration['connect_retry_delay_ms'] ?? config('site-monitoring.ssl_connect_retry_delay_ms', 500)));

        $lastErrno = 0;
        $lastErrstr = '';
        $ms = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $start = hrtime(true);
            $ctx = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => $verifyPeer,
                    'verify_peer_name' => $verifyPeer,
                    'SNI_enabled' => true,
                    'peer_name' => $host,
                ],
            ]);

            $socket = @stream_socket_client(
                'ssl://'.$host.':'.$port,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $ctx
            );

            $ms = (int) round((hrtime(true) - $start) / 1_000_000);

            if ($socket !== false) {
                return $this->parseCertificate($socket, $host, $port, $ms, $attempt);
            }

            $lastErrno = $errno;
            $lastErrstr = $errstr;

            if ($attempt < $maxAttempts) {
                usleep($delayMs * 1000);
            }
        }

        return new ProbeResult(
            MonitoringLogStatus::Failed,
            'TLS handshake failed after '.$maxAttempts.' attempt(s): '.($lastErrstr ?: 'unknown error'),
            null,
            $ms,
            ['host' => $host, 'port' => $port, 'errno' => $lastErrno, 'ssl_connect_attempts' => $maxAttempts]
        );
    }

    private function parseCertificate(mixed $socket, string $host, int $port, int $ms, int $attemptsUsed): ProbeResult
    {
        $params = stream_context_get_params($socket);
        fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($cert === null) {
            return new ProbeResult(MonitoringLogStatus::Error, 'Peer certificate not captured.', null, $ms, ['host' => $host, 'ssl_connect_attempts' => $attemptsUsed]);
        }

        $parsed = openssl_x509_parse($cert);

        if ($parsed === false || ! isset($parsed['validTo_time_t'])) {
            return new ProbeResult(MonitoringLogStatus::Error, 'Could not parse X.509 certificate.', null, $ms, ['host' => $host]);
        }

        $validTo = (new DateTimeImmutable('@'.$parsed['validTo_time_t']))->setTimezone(new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $daysRemaining = (int) floor(($validTo->getTimestamp() - $now->getTimestamp()) / 86400);

        $warningDays = (int) config('site-monitoring.ssl_warning_days', 30);

        $meta = [
            'host' => $host,
            'port' => $port,
            'ssl_not_after' => $validTo->format(DateTimeImmutable::ATOM),
            'ssl_days_remaining' => $daysRemaining,
            'ssl_connect_attempts' => $attemptsUsed,
        ];

        if ($daysRemaining < 0) {
            return new ProbeResult(
                MonitoringLogStatus::Failed,
                'SSL certificate expired.',
                null,
                $ms,
                $meta
            );
        }

        if ($daysRemaining <= $warningDays) {
            return new ProbeResult(
                MonitoringLogStatus::Degraded,
                'SSL certificate expires in '.$daysRemaining.' day(s).',
                null,
                $ms,
                $meta
            );
        }

        return new ProbeResult(
            MonitoringLogStatus::Ok,
            'SSL certificate valid ('.$daysRemaining.' days remaining).',
            null,
            $ms,
            $meta
        );
    }
}
