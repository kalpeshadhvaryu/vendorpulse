<?php

namespace App\SiteMonitoring\Checks;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\Contracts\CheckStrategyInterface;
use App\SiteMonitoring\DTO\ProbeResult;
use App\SiteMonitoring\Enums\MonitoringLogStatus;

class TcpPortCheckStrategy implements CheckStrategyInterface
{
    public function execute(MonitoringCheck $check): ProbeResult
    {
        $endpoint = trim((string) $check->endpoint);
        if ($endpoint === '') {
            return ProbeResult::skipped('Missing endpoint for TCP check.');
        }

        $configuration = is_array($check->configuration) ? $check->configuration : [];
        [$host, $parsedPort] = $this->parseHostAndPort($endpoint);

        if ($host === '') {
            return ProbeResult::error('Unable to parse host from endpoint.');
        }

        $configPort = isset($configuration['port']) ? (int) $configuration['port'] : null;
        $port = $this->normalizePort($configPort ?? $parsedPort ?? 80);

        $timeoutMs = isset($configuration['timeout_ms'])
            ? (int) $configuration['timeout_ms']
            : (int) config('site-monitoring.tcp_timeout_ms', 5000);
        $timeoutMs = max(500, min($timeoutMs, 60000));

        $address = $this->buildTcpAddress($host, $port);
        $timeoutSeconds = $timeoutMs / 1000;

        $start = hrtime(true);
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
        );

        $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

        $meta = [
            'host' => $host,
            'port' => $port,
            'address' => $address,
            'timeout_ms' => $timeoutMs,
            'probe_type' => 'tcp_connect',
        ];

        if ($socket !== false) {
            fclose($socket);

            return new ProbeResult(
                MonitoringLogStatus::Ok,
                "TCP connect succeeded ({$host}:{$port})",
                null,
                $latencyMs,
                $meta,
            );
        }

        $errorText = $errstr !== '' ? $errstr : 'Connection failed';
        if ($latencyMs >= $timeoutMs) {
            $errorText = 'Connection timeout after '.$timeoutMs.'ms';
        }

        return new ProbeResult(
            MonitoringLogStatus::Failed,
            "TCP connect failed ({$host}:{$port}): {$errorText}",
            null,
            $latencyMs,
            array_merge($meta, [
                'socket_error_code' => $errno,
                'socket_error' => $errstr,
            ]),
        );
    }

    /**
     * @return array{0:string,1:int|null}
     */
    private function parseHostAndPort(string $endpoint): array
    {
        $value = trim($endpoint);

        if (preg_match('#^https?://#i', $value)) {
            $host = (string) parse_url($value, PHP_URL_HOST);
            $port = parse_url($value, PHP_URL_PORT);

            return [trim($host), is_int($port) ? $port : null];
        }

        if (preg_match('/^\[([0-9a-fA-F:]+)\](?::(\d+))?$/', $value, $match) === 1) {
            return [$match[1], isset($match[2]) ? (int) $match[2] : null];
        }

        $withoutPath = explode('/', $value, 2)[0] ?? $value;

        if (substr_count($withoutPath, ':') === 1) {
            [$host, $port] = explode(':', $withoutPath, 2);
            if ($host !== '' && ctype_digit($port)) {
                return [trim($host), (int) $port];
            }
        }

        return [trim($withoutPath), null];
    }

    private function normalizePort(int $port): int
    {
        if ($port < 1 || $port > 65535) {
            return 80;
        }

        return $port;
    }

    private function buildTcpAddress(string $host, int $port): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return "tcp://[{$host}]:{$port}";
        }

        return "tcp://{$host}:{$port}";
    }
}
