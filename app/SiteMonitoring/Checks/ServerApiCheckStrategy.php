<?php

namespace App\SiteMonitoring\Checks;

use App\Models\MonitoringCheck;
use App\SiteMonitoring\Contracts\CheckStrategyInterface;
use App\SiteMonitoring\DTO\ProbeResult;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class ServerApiCheckStrategy implements CheckStrategyInterface
{
    public function execute(MonitoringCheck $check): ProbeResult
    {
        $cfg = is_array($check->configuration) ? $check->configuration : [];

        $provider = strtolower((string) ($cfg['server_provider'] ?? 'cpanel'));
        $hostname = trim((string) ($cfg['hostname'] ?? parse_url((string) $check->endpoint, PHP_URL_HOST) ?? ''));
        $os = trim((string) ($cfg['os'] ?? 'linux'));

        $baseUrl = trim((string) ($cfg['api_base_url'] ?? $check->endpoint ?? ''));
        if ($baseUrl === '') {
            return ProbeResult::skipped('Missing API base URL for server check.');
        }

        $apiPath = ltrim((string) ($cfg['api_path'] ?? ''), '/');
        if ($apiPath === '') {
            $apiPath = $provider === 'cpanel' ? 'execute/ResourceUsage/get_usages' : 'metrics';
        }

        $url = rtrim($baseUrl, '/').'/'.$apiPath;

        $verifySsl = (bool) ($cfg['verify_ssl'] ?? true);
        $timeout = max(1, min((int) ($cfg['timeout_seconds'] ?? 20), 120));

        $request = Http::timeout($timeout)->withOptions([
            'verify' => $verifySsl,
        ]);

        $request = $this->applyAuth($request, $cfg, $provider);
        $request = $this->applyHeaders($request, $cfg);

        $responseTimeMs = null;

        try {
            $start = hrtime(true);
            $response = $request->get($url);
            $responseTimeMs = (int) round((hrtime(true) - $start) / 1_000_000);
        } catch (Throwable $e) {
            return new ProbeResult(
                MonitoringLogStatus::Error,
                mb_substr($e->getMessage(), 0, 500),
                null,
                $responseTimeMs,
                [
                    'server' => [
                        'provider' => $provider,
                        'hostname' => $hostname,
                        'os' => $os,
                        'api_url' => $url,
                    ],
                    'exception' => $e::class,
                ]
            );
        }

        if (! $response->successful()) {
            return new ProbeResult(
                MonitoringLogStatus::Failed,
                'Server API returned HTTP '.$response->status(),
                $response->status(),
                $responseTimeMs,
                [
                    'server' => [
                        'provider' => $provider,
                        'hostname' => $hostname,
                        'os' => $os,
                        'api_url' => $url,
                    ],
                ]
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            return new ProbeResult(
                MonitoringLogStatus::Error,
                'Server API response is not JSON object/array.',
                $response->status(),
                $responseTimeMs,
                [
                    'server' => [
                        'provider' => $provider,
                        'hostname' => $hostname,
                        'os' => $os,
                        'api_url' => $url,
                    ],
                ]
            );
        }

        $paths = $this->metricPaths($cfg, $provider);
        $metrics = [];

        foreach ($paths as $metric => $path) {
            $value = data_get($json, $path);
            if (is_numeric($value)) {
                $metrics[$metric] = (float) $value;
            }
        }

        if ($metrics === []) {
            return new ProbeResult(
                MonitoringLogStatus::Degraded,
                'Server API reachable but no numeric metrics extracted. Configure metrics_paths.',
                $response->status(),
                $responseTimeMs,
                [
                    'server' => [
                        'provider' => $provider,
                        'hostname' => $hostname,
                        'os' => $os,
                        'api_url' => $url,
                    ],
                    'metric_paths' => $paths,
                ]
            );
        }

        $thresholds = is_array($cfg['thresholds'] ?? null) ? $cfg['thresholds'] : [];
        [$status, $breaches] = $this->evaluateThresholds($metrics, $thresholds);

        $statusMessage = $this->buildStatusMessage($status, $metrics, $breaches);

        return new ProbeResult(
            $status,
            $statusMessage,
            $response->status(),
            $responseTimeMs,
            [
                'server' => [
                    'provider' => $provider,
                    'hostname' => $hostname,
                    'os' => $os,
                    'api_url' => $url,
                ],
                'metrics' => $metrics,
                'threshold_breaches' => $breaches,
                'metric_paths' => $paths,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function applyAuth(PendingRequest $request, array $cfg, string $provider): PendingRequest
    {
        $authType = strtolower((string) ($cfg['auth_type'] ?? ($provider === 'cpanel' ? 'cpanel_token' : 'none')));

        return match ($authType) {
            'cpanel_token' => $this->withCpanelToken($request, $cfg),
            'whm_token' => $this->withWhmToken($request, $cfg),
            'bearer' => isset($cfg['api_token']) && $cfg['api_token'] !== ''
                ? $request->withToken((string) $cfg['api_token'])
                : $request,
            'basic' => (isset($cfg['api_username'], $cfg['api_password']) && $cfg['api_username'] !== '' && $cfg['api_password'] !== '')
                ? $request->withBasicAuth((string) $cfg['api_username'], (string) $cfg['api_password'])
                : $request,
            default => $request,
        };
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function withCpanelToken(PendingRequest $request, array $cfg): PendingRequest
    {
        $username = trim((string) ($cfg['api_username'] ?? ''));
        $token = trim((string) ($cfg['api_token'] ?? ''));

        if ($username === '' || $token === '') {
            return $request;
        }

        return $request->withHeaders([
            'Authorization' => 'cpanel '.$username.':'.$token,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function withWhmToken(PendingRequest $request, array $cfg): PendingRequest
    {
        $username = trim((string) ($cfg['api_username'] ?? ''));
        $token = trim((string) ($cfg['api_token'] ?? ''));

        if ($username === '' || $token === '') {
            return $request;
        }

        return $request->withHeaders([
            'Authorization' => 'whm '.$username.':'.$token,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function applyHeaders(PendingRequest $request, array $cfg): PendingRequest
    {
        $headers = [];
        $configured = $cfg['headers'] ?? null;

        if (is_array($configured)) {
            foreach ($configured as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }
                if (is_string($value) || is_numeric($value) || is_bool($value)) {
                    $headers[$key] = (string) $value;
                }
            }
        }

        return $headers === [] ? $request : $request->withHeaders($headers);
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, string>
     */
    private function metricPaths(array $cfg, string $provider): array
    {
        $default = $provider === 'cpanel'
            ? [
                'load_1m' => 'data.one',
                'load_5m' => 'data.five',
                'load_15m' => 'data.fifteen',
                'memory_used_percent' => 'data.memory.used_percent',
                'disk_used_percent' => 'data.disk.used_percent',
                'bandwidth_used_bytes' => 'data.bandwidth.used_bytes',
                'process_count' => 'data.processes.count',
            ]
            : [
                'load_1m' => 'metrics.load_1m',
                'load_5m' => 'metrics.load_5m',
                'load_15m' => 'metrics.load_15m',
                'cpu_percent' => 'metrics.cpu_percent',
                'memory_used_percent' => 'metrics.memory_used_percent',
                'disk_used_percent' => 'metrics.disk_used_percent',
                'bandwidth_used_bytes' => 'metrics.bandwidth_used_bytes',
                'process_count' => 'metrics.process_count',
            ];

        $configured = $cfg['metrics_paths'] ?? [];
        if (! is_array($configured)) {
            return $default;
        }

        $paths = $default;
        foreach ($configured as $metric => $path) {
            if (! is_string($metric) || ! is_string($path) || trim($path) === '') {
                continue;
            }
            $paths[$metric] = trim($path);
        }

        return $paths;
    }

    /**
     * @param  array<string, float>  $metrics
     * @param  array<string, mixed>  $thresholds
     * @return array{0: MonitoringLogStatus, 1: array<string, string>}
     */
    private function evaluateThresholds(array $metrics, array $thresholds): array
    {
        $breaches = [];
        $status = MonitoringLogStatus::Ok;

        $rules = [
            'cpu_percent' => ['warn' => 85.0, 'critical' => 95.0],
            'memory_used_percent' => ['warn' => 85.0, 'critical' => 95.0],
            'disk_used_percent' => ['warn' => 85.0, 'critical' => 95.0],
            'load_1m' => ['warn' => 4.0, 'critical' => 8.0],
        ];

        foreach ($rules as $metric => $defaults) {
            if (! isset($metrics[$metric])) {
                continue;
            }

            $metricThresholds = is_array($thresholds[$metric] ?? null) ? $thresholds[$metric] : [];
            $warn = is_numeric($metricThresholds['warn'] ?? null) ? (float) $metricThresholds['warn'] : $defaults['warn'];
            $critical = is_numeric($metricThresholds['critical'] ?? null) ? (float) $metricThresholds['critical'] : $defaults['critical'];

            $value = $metrics[$metric];
            if ($value >= $critical) {
                $breaches[$metric] = 'critical';
                $status = MonitoringLogStatus::Failed;
                continue;
            }

            if ($value >= $warn && $status !== MonitoringLogStatus::Failed) {
                $breaches[$metric] = 'warning';
                $status = MonitoringLogStatus::Degraded;
            }
        }

        return [$status, $breaches];
    }

    /**
     * @param  array<string, float>  $metrics
     * @param  array<string, string>  $breaches
     */
    private function buildStatusMessage(MonitoringLogStatus $status, array $metrics, array $breaches): string
    {
        $parts = [];

        foreach (['load_1m', 'cpu_percent', 'memory_used_percent', 'disk_used_percent', 'process_count'] as $key) {
            if (isset($metrics[$key])) {
                $parts[] = $key.': '.round($metrics[$key], 2);
            }
        }

        if ($status === MonitoringLogStatus::Failed) {
            return 'Server metric critical threshold reached ('.implode(', ', array_keys($breaches)).'). '.implode(' | ', $parts);
        }

        if ($status === MonitoringLogStatus::Degraded) {
            return 'Server metric warning threshold reached ('.implode(', ', array_keys($breaches)).'). '.implode(' | ', $parts);
        }

        return 'Server metrics healthy. '.implode(' | ', $parts);
    }
}
