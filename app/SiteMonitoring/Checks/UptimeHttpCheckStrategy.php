<?php

namespace App\SiteMonitoring\Checks;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\Contracts\CheckStrategyInterface;
use App\SiteMonitoring\DTO\ProbeResult;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use App\SiteMonitoring\Support\MonitoringEndpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class UptimeHttpCheckStrategy implements CheckStrategyInterface
{
    public function execute(MonitoringCheck $check): ProbeResult
    {
        $endpoint = $check->endpoint;
        if ($endpoint === null || $endpoint === '') {
            return ProbeResult::skipped('Missing endpoint for uptime check.');
        }

        $configuration = $check->configuration ?? [];
        $method = strtoupper((string) ($configuration['method'] ?? 'HEAD'));
        if (! in_array($method, ['GET', 'HEAD'], true)) {
            $method = 'HEAD';
        }

        $timeout = (int) ($configuration['timeout_seconds'] ?? 15);
        $timeout = max(1, min($timeout, 120));

        $expected = $configuration['expected_status'] ?? 200;
        $expectedCodes = is_array($expected) ? $expected : [(int) $expected];

        $url = MonitoringEndpoint::toHttpUrl($endpoint, (string) $check->type);

        $maxAttempts = max(1, (int) ($configuration['retry_attempts'] ?? config('site-monitoring.uptime_http_retries', 3)));
        $delayMs = max(0, (int) ($configuration['retry_delay_ms'] ?? config('site-monitoring.uptime_http_retry_delay_ms', 400)));

        $lastThrowable = null;
        $lastResponse = null;
        $lastMs = null;
        $attemptsUsed = $maxAttempts;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $start = hrtime(true);

            try {
                $pending = Http::timeout($timeout)
                    ->withOptions(['allow_redirects' => (bool) ($configuration['follow_redirects'] ?? true)]);

                $response = match ($method) {
                    'GET' => $pending->get($url),
                    default => $pending->head($url),
                };
            } catch (Throwable $e) {
                $lastThrowable = $e;
                $lastMs = (int) round((hrtime(true) - $start) / 1_000_000);
                if ($this->shouldRetryTransport($e) && $attempt < $maxAttempts) {
                    usleep($delayMs * 1000);

                    continue;
                }

                return new ProbeResult(
                    MonitoringLogStatus::Failed,
                    $e->getMessage(),
                    null,
                    $lastMs,
                    array_merge(
                        ['url' => $url, 'method' => $method, 'exception' => $e::class, 'attempts' => $attempt],
                        $this->buildHttpLogMeta(null, $method, $configuration)
                    )
                );
            }

            $lastMs = (int) round((hrtime(true) - $start) / 1_000_000);
            $code = $response->status();

            if ($this->shouldRetryHttpStatus($code) && $attempt < $maxAttempts) {
                usleep($delayMs * 1000);

                continue;
            }

            $lastResponse = $response;
            $attemptsUsed = $attempt;

            break;
        }

        if (! $lastResponse instanceof Response) {
            return new ProbeResult(
                MonitoringLogStatus::Error,
                $lastThrowable?->getMessage() ?? 'No HTTP response.',
                null,
                $lastMs,
                ['url' => $url, 'method' => $method]
            );
        }

        $code = $lastResponse->status();
        $baseMeta = array_merge(
            ['url' => $url, 'method' => $method, 'attempts' => $attemptsUsed],
            $this->buildHttpLogMeta($lastResponse, $method, $configuration)
        );

        if (in_array($code, $expectedCodes, true)) {
            return new ProbeResult(
                MonitoringLogStatus::Ok,
                'HTTP '.$code,
                $code,
                $lastMs,
                $baseMeta
            );
        }

        return new ProbeResult(
            MonitoringLogStatus::Failed,
            'Unexpected HTTP status '.$code.' (expected: '.implode(',', $expectedCodes).')',
            $code,
            $lastMs,
            $baseMeta
        );
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function buildHttpLogMeta(?Response $response, string $method, array $configuration): array
    {
        if ($response === null) {
            return [];
        }

        $maxHeaders = max(0, (int) config('site-monitoring.http_log_header_max_lines', 20));
        $headers = [];
        $count = 0;

        foreach ($response->headers() as $name => $values) {
            if ($count >= $maxHeaders) {
                break;
            }

            $headers[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
            $count++;
        }

        $effective = $response->effectiveUri();
        $meta = [
            'http_effective_url' => $effective !== null ? (string) $effective : null,
            'http_response_headers' => $headers,
        ];

        $logBody = (bool) ($configuration['log_response_body'] ?? true);
        if ($method === 'GET' && $logBody) {
            $maxBytes = max(0, (int) ($configuration['log_response_body_max_bytes'] ?? config('site-monitoring.http_log_body_max_bytes', 4096)));
            if ($maxBytes > 0) {
                $body = (string) $response->body();
                $meta['http_response_size_bytes'] = strlen($body);
                $meta['http_response_body_preview'] = mb_substr($body, 0, $maxBytes);
                if (strlen($body) > $maxBytes) {
                    $meta['http_response_body_truncated'] = true;
                }
            }
        }

        return $meta;
    }

    private function shouldRetryTransport(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'timed out')
            || str_contains($msg, 'timeout')
            || str_contains($msg, 'connection refused')
            || str_contains($msg, 'could not resolve host')
            || str_contains($msg, 'operation timed out');
    }

    private function shouldRetryHttpStatus(int $code): bool
    {
        return $code === 429 || ($code >= 500 && $code <= 599);
    }
}
