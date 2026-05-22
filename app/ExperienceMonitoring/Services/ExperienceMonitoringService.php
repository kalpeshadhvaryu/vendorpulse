<?php

namespace App\ExperienceMonitoring\Services;

use App\Models\ExperienceMonitoringTest;
use App\Models\User;
use App\Models\WebsiteSpeedtestRun;
use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use App\Support\Organization\CurrentOrganization;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ExperienceMonitoringService
{
    public function __construct(
        protected ExperienceMonitoringRepositoryInterface $repository,
        protected CurrentOrganization $currentOrganization,
    ) {}

    public function paginateTests(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginateTests($perPage, $filters);
    }

    public function createTest(array $data, User $actor): ExperienceMonitoringTest
    {
        $organizationId = $this->currentOrganization->id();
        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organization context is required to create an experience monitoring test.',
            ]);
        }

        return $this->repository->createTest([
            ...$data,
            'organization_id' => $organizationId,
            'next_run_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function updateTest(ExperienceMonitoringTest $test, array $data, User $actor): ExperienceMonitoringTest
    {
        return $this->repository->updateTest($test, [
            ...$data,
            'updated_by' => $actor->id,
        ]);
    }

    public function deleteTest(ExperienceMonitoringTest $test): bool
    {
        return $this->repository->deleteTest($test);
    }

    public function paginateRuns(ExperienceMonitoringTest $test, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginateRuns($test, $perPage, $filters);
    }

    public function listScreenshots(ExperienceMonitoringTest $test, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->listScreenshots($test, $perPage);
    }

    public function summarizeMetrics(ExperienceMonitoringTest $test, Carbon $from, Carbon $to): array
    {
        $runs = $this->repository->paginateRuns($test, 1000, [
            'from' => $from,
            'to' => $to,
        ])->items();

        $loginDurations = [];
        $dashboardDurations = [];
        $totalDurations = [];

        foreach ($runs as $run) {
            if ($run->login_duration_ms !== null) {
                $loginDurations[] = (float) $run->login_duration_ms;
            }
            if ($run->dashboard_load_duration_ms !== null) {
                $dashboardDurations[] = (float) $run->dashboard_load_duration_ms;
            }
            if ($run->total_duration_ms !== null) {
                $totalDurations[] = (float) $run->total_duration_ms;
            }
        }

        return [
            'window_from' => $from->toIso8601String(),
            'window_to' => $to->toIso8601String(),
            'sample_count' => count($runs),
            'login_duration_ms' => $this->aggregateSeries($loginDurations),
            'dashboard_load_duration_ms' => $this->aggregateSeries($dashboardDurations),
            'total_duration_ms' => $this->aggregateSeries($totalDurations),
        ];
    }

    public function buildTechnicalReport(ExperienceMonitoringTest $test, Carbon $from, Carbon $to): array
    {
        $runs = $this->repository->paginateRuns($test, 1000, [
            'from' => $from,
            'to' => $to,
        ])->items();

        $sampleCount = count($runs);
        $successCount = 0;
        $statusBreakdown = [];
        $loginDurations = [];
        $dashboardDurations = [];
        $totalDurations = [];
        $failedRequestsCounts = [];
        $jsErrorsCounts = [];
        $httpStatusDistribution = [];
        $errorSignatures = [];

        foreach ($runs as $run) {
            $status = (string) ($run->status instanceof \BackedEnum ? $run->status->value : $run->status);
            $statusBreakdown[$status] = ($statusBreakdown[$status] ?? 0) + 1;

            if ($status === 'ok') {
                $successCount++;
            }

            if ($run->login_duration_ms !== null) {
                $loginDurations[] = (float) $run->login_duration_ms;
            }
            if ($run->dashboard_load_duration_ms !== null) {
                $dashboardDurations[] = (float) $run->dashboard_load_duration_ms;
            }
            if ($run->total_duration_ms !== null) {
                $totalDurations[] = (float) $run->total_duration_ms;
            }

            $failedRequestsCounts[] = (int) $run->failed_requests_count;
            $jsErrorsCounts[] = (int) $run->js_errors_count;

            if ($run->http_status !== null) {
                $httpStatusDistribution[(string) $run->http_status] = ($httpStatusDistribution[(string) $run->http_status] ?? 0) + 1;
            }

            foreach ((array) ($run->http_status_codes ?? []) as $entry) {
                if (! is_array($entry) || ! isset($entry['status'])) {
                    continue;
                }

                $statusCode = is_numeric($entry['status']) ? (string) ((int) $entry['status']) : null;
                if (! $statusCode) {
                    continue;
                }

                $httpStatusDistribution[$statusCode] = ($httpStatusDistribution[$statusCode] ?? 0) + 1;
            }

            $candidateErrors = [];
            if (is_string($run->error_message) && trim($run->error_message) !== '') {
                $candidateErrors[] = trim($run->error_message);
            }
            foreach ((array) ($run->browser_logs ?? []) as $log) {
                if (! is_array($log)) {
                    continue;
                }
                if (strtolower((string) ($log['level'] ?? '')) !== 'error') {
                    continue;
                }
                $message = trim((string) ($log['message'] ?? ''));
                if ($message !== '') {
                    $candidateErrors[] = $message;
                }
            }

            foreach (array_unique($candidateErrors) as $message) {
                $errorSignatures[$message] = ($errorSignatures[$message] ?? 0) + 1;
            }
        }

        arsort($statusBreakdown);
        arsort($httpStatusDistribution);
        arsort($errorSignatures);

        $successRatePct = $sampleCount > 0 ? round(($successCount / $sampleCount) * 100, 2) : null;
        $metrics = [
            'login_duration_ms' => $this->aggregateSeries($loginDurations),
            'dashboard_load_duration_ms' => $this->aggregateSeries($dashboardDurations),
            'total_duration_ms' => $this->aggregateSeries($totalDurations),
        ];

        $host = $this->extractHost($test->dashboard_url) ?? $this->extractHost($test->login_url);
        $dnsDiagnostics = $host ? $this->resolveDnsDiagnostics($host) : null;
        $tlsDiagnostics = $host ? $this->resolveTlsDiagnostics($host) : null;
        $securityHeaders = $this->fetchSecurityHeaders($test->dashboard_url ?: $test->login_url);
        $latestSpeedtest = $host ? $this->latestSpeedtestForHost((string) $test->organization_id, $host) : null;

        $availabilityRisk = $this->availabilityRisk($successRatePct);
        $performanceRisk = $this->performanceRisk($metrics['dashboard_load_duration_ms']['p95'], $metrics['login_duration_ms']['p95']);
        $frontendRisk = $this->frontendRisk($sampleCount > 0 ? array_sum($jsErrorsCounts) / $sampleCount : null, $sampleCount > 0 ? array_sum($failedRequestsCounts) / $sampleCount : null);
        $securityRisk = $this->securityRisk($securityHeaders, $tlsDiagnostics);

        $riskScore = round(
            ($availabilityRisk * 0.30)
            + ($performanceRisk * 0.25)
            + ($frontendRisk * 0.20)
            + ($securityRisk * 0.25),
            2
        );

        $severity = $this->severityFromScore($riskScore);
        $findings = $this->buildFindings(
            $severity,
            $successRatePct,
            $metrics,
            $securityHeaders,
            $tlsDiagnostics,
            $errorSignatures,
            $sampleCount > 0 ? array_sum($failedRequestsCounts) / $sampleCount : null
        );

        return [
            'generated_at' => now()->toIso8601String(),
            'window_from' => $from->toIso8601String(),
            'window_to' => $to->toIso8601String(),
            'sample_count' => $sampleCount,
            'severity' => $severity,
            'risk_score' => $riskScore,
            'score_breakdown' => [
                'availability' => $availabilityRisk,
                'performance' => $performanceRisk,
                'frontend' => $frontendRisk,
                'security' => $securityRisk,
            ],
            'journey' => [
                'success_rate_pct' => $successRatePct,
                'status_breakdown' => $statusBreakdown,
                'metrics' => $metrics,
                'failed_requests_avg' => $sampleCount > 0 ? round(array_sum($failedRequestsCounts) / $sampleCount, 2) : null,
                'js_errors_avg' => $sampleCount > 0 ? round(array_sum($jsErrorsCounts) / $sampleCount, 2) : null,
                'http_status_distribution' => $httpStatusDistribution,
                'top_js_errors' => array_slice(
                    array_map(
                        static fn (string $message, int $count): array => ['message' => $message, 'count' => $count],
                        array_keys($errorSignatures),
                        array_values($errorSignatures)
                    ),
                    0,
                    8
                ),
            ],
            'network' => [
                'dns' => $dnsDiagnostics,
                'tls' => $tlsDiagnostics,
                'security_headers' => $securityHeaders,
                'latest_speedtest' => $latestSpeedtest,
            ],
            'findings' => $findings,
            'suggestions' => $this->buildSuggestions($findings),
        ];
    }

    private function aggregateSeries(array $values): array
    {
        if ($values === []) {
            return [
                'avg' => null,
                'min' => null,
                'max' => null,
                'p95' => null,
            ];
        }

        sort($values);
        $count = count($values);
        $p95Index = max(0, (int) ceil($count * 0.95) - 1);

        return [
            'avg' => round(array_sum($values) / $count, 2),
            'min' => round((float) min($values), 2),
            'max' => round((float) max($values), 2),
            'p95' => round((float) $values[$p95Index], 2),
        ];
    }

    private function availabilityRisk(?float $successRatePct): float
    {
        if ($successRatePct === null) {
            return 40.0;
        }
        if ($successRatePct >= 99.5) {
            return 5.0;
        }
        if ($successRatePct >= 98.0) {
            return 25.0;
        }
        if ($successRatePct >= 95.0) {
            return 60.0;
        }

        return 90.0;
    }

    private function performanceRisk(?float $dashboardP95, ?float $loginP95): float
    {
        $score = 0.0;

        if ($dashboardP95 !== null) {
            if ($dashboardP95 >= 10000) {
                $score += 70;
            } elseif ($dashboardP95 >= 7000) {
                $score += 50;
            } elseif ($dashboardP95 >= 3000) {
                $score += 25;
            } else {
                $score += 8;
            }
        } else {
            $score += 20;
        }

        if ($loginP95 !== null) {
            if ($loginP95 >= 4000) {
                $score += 30;
            } elseif ($loginP95 >= 2000) {
                $score += 18;
            } else {
                $score += 6;
            }
        } else {
            $score += 10;
        }

        return min(100, round($score, 2));
    }

    private function frontendRisk(?float $jsErrorsAvg, ?float $failedRequestsAvg): float
    {
        $score = 0.0;

        if ($jsErrorsAvg !== null) {
            if ($jsErrorsAvg > 10) {
                $score += 65;
            } elseif ($jsErrorsAvg > 3) {
                $score += 35;
            } else {
                $score += 10;
            }
        } else {
            $score += 15;
        }

        if ($failedRequestsAvg !== null) {
            if ($failedRequestsAvg > 3) {
                $score += 35;
            } elseif ($failedRequestsAvg >= 1) {
                $score += 20;
            } else {
                $score += 8;
            }
        } else {
            $score += 10;
        }

        return min(100, round($score, 2));
    }

    private function securityRisk(array $securityHeaders, ?array $tlsDiagnostics): float
    {
        $score = 0.0;
        $missing = (array) ($securityHeaders['missing_critical'] ?? []);

        if (in_array('content-security-policy', $missing, true) && in_array('strict-transport-security', $missing, true)) {
            $score += 55;
        } else {
            $score += count($missing) * 10;
        }

        $daysRemaining = $tlsDiagnostics['certificate_days_remaining'] ?? null;
        if (is_numeric($daysRemaining)) {
            $daysRemaining = (int) $daysRemaining;
            if ($daysRemaining < 7) {
                $score += 40;
            } elseif ($daysRemaining < 14) {
                $score += 28;
            } elseif ($daysRemaining < 30) {
                $score += 15;
            } else {
                $score += 5;
            }
        } else {
            $score += 20;
        }

        return min(100, round($score, 2));
    }

    private function severityFromScore(float $score): string
    {
        if ($score >= 80) {
            return 'S0';
        }
        if ($score >= 60) {
            return 'S1';
        }
        if ($score >= 40) {
            return 'S2';
        }
        if ($score >= 20) {
            return 'S3';
        }

        return 'S4';
    }

    private function buildFindings(
        string $severity,
        ?float $successRatePct,
        array $metrics,
        array $securityHeaders,
        ?array $tlsDiagnostics,
        array $errorSignatures,
        ?float $failedRequestsAvg
    ): array {
        $findings = [];

        if ($successRatePct !== null && $successRatePct < 98) {
            $findings[] = [
                'title' => 'Journey success rate is below expected threshold.',
                'severity' => $successRatePct < 95 ? 'S1' : 'S2',
                'detail' => 'Success rate is '.$successRatePct.'% in the selected window.',
            ];
        }

        $dashboardP95 = $metrics['dashboard_load_duration_ms']['p95'] ?? null;
        if (is_numeric($dashboardP95) && (float) $dashboardP95 >= 7000) {
            $findings[] = [
                'title' => 'Dashboard load p95 is elevated.',
                'severity' => (float) $dashboardP95 >= 10000 ? 'S1' : 'S2',
                'detail' => 'Dashboard load p95 is '.(float) $dashboardP95.' ms.',
            ];
        }

        $missingHeaders = (array) ($securityHeaders['missing_critical'] ?? []);
        if ($missingHeaders !== []) {
            $findings[] = [
                'title' => 'Critical security headers are missing.',
                'severity' => in_array('content-security-policy', $missingHeaders, true) ? 'S1' : 'S2',
                'detail' => 'Missing headers: '.implode(', ', $missingHeaders).'.',
            ];
        }

        $daysRemaining = $tlsDiagnostics['certificate_days_remaining'] ?? null;
        if (is_numeric($daysRemaining) && (int) $daysRemaining < 14) {
            $findings[] = [
                'title' => 'TLS certificate expiry is approaching.',
                'severity' => (int) $daysRemaining < 7 ? 'S0' : 'S1',
                'detail' => 'Certificate expires in '.(int) $daysRemaining.' days.',
            ];
        }

        if ($failedRequestsAvg !== null && $failedRequestsAvg >= 1) {
            $findings[] = [
                'title' => 'Failed network requests detected during user journey.',
                'severity' => $failedRequestsAvg > 3 ? 'S1' : 'S2',
                'detail' => 'Average failed requests per run: '.round($failedRequestsAvg, 2).'.',
            ];
        }

        if ($errorSignatures !== []) {
            $topMessage = (string) array_key_first($errorSignatures);
            $topCount = (int) ($errorSignatures[$topMessage] ?? 0);
            $findings[] = [
                'title' => 'Recurring JavaScript error signatures observed.',
                'severity' => $topCount >= 5 ? 'S1' : 'S2',
                'detail' => 'Top error seen '.$topCount.' times: '.$topMessage,
            ];
        }

        if ($findings === []) {
            $findings[] = [
                'title' => 'No high-risk patterns detected in selected window.',
                'severity' => $severity,
                'detail' => 'Continue tracking trends to catch regressions early.',
            ];
        }

        return $findings;
    }

    private function buildSuggestions(array $findings): array
    {
        $suggestions = [];

        foreach ($findings as $finding) {
            $text = strtolower((string) ($finding['title'] ?? ''));

            if (str_contains($text, 'success rate')) {
                $suggestions[] = 'Compare failed runs against the latest successful run and prioritize errors introduced after the most recent deployment.';
            }
            if (str_contains($text, 'dashboard load')) {
                $suggestions[] = 'Run endpoint-level profiling for dashboard APIs and verify cache hit rates for top widgets.';
            }
            if (str_contains($text, 'security headers')) {
                $suggestions[] = 'Enable missing headers in reverse proxy/app middleware and re-validate with a follow-up speedtest.';
            }
            if (str_contains($text, 'tls certificate')) {
                $suggestions[] = 'Renew certificate and verify the full chain from all edge nodes before expiry threshold.';
            }
            if (str_contains($text, 'network requests')) {
                $suggestions[] = 'Inspect failing endpoints for 4xx/5xx patterns and validate DNS/connect/TLS timing for those hosts.';
            }
            if (str_contains($text, 'javascript error')) {
                $suggestions[] = 'Group stack traces by signature and add defensive null checks around failing frontend code paths.';
            }
        }

        if ($suggestions === []) {
            $suggestions[] = 'No immediate corrective action required; keep monitoring and review trend changes every 24 hours.';
        }

        return array_values(array_unique($suggestions));
    }

    private function extractHost(?string $url): ?string
    {
        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function resolveDnsDiagnostics(string $host): array
    {
        $fqdn = rtrim($host, '.').'.';

        $started = microtime(true);
        $records = @dns_get_record($fqdn, DNS_A + DNS_AAAA);
        $latencyMs = round((microtime(true) - $started) * 1000, 2);

        $aRecords = [];
        $aaaaRecords = [];
        $ttls = [];

        if (is_array($records)) {
            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $ttl = isset($record['ttl']) && is_numeric($record['ttl']) ? (int) $record['ttl'] : null;
                if ($ttl !== null) {
                    $ttls[] = $ttl;
                }

                if (($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                    $aRecords[] = (string) $record['ip'];
                }

                if (($record['type'] ?? null) === 'AAAA' && isset($record['ipv6'])) {
                    $aaaaRecords[] = (string) $record['ipv6'];
                }
            }
        }

        return [
            'host' => $host,
            'lookup_latency_ms' => $latencyMs,
            'resolver_nameservers' => $this->resolverNameservers(),
            'a_records' => array_values(array_unique($aRecords)),
            'aaaa_records' => array_values(array_unique($aaaaRecords)),
            'ttl_min' => $ttls !== [] ? min($ttls) : null,
            'ttl_max' => $ttls !== [] ? max($ttls) : null,
        ];
    }

    private function resolverNameservers(): array
    {
        $resolvConf = @file('/etc/resolv.conf', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($resolvConf)) {
            return [];
        }

        $nameservers = [];
        foreach ($resolvConf as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'nameserver ')) {
                continue;
            }
            $parts = preg_split('/\s+/', $line);
            if (isset($parts[1]) && $parts[1] !== '') {
                $nameservers[] = $parts[1];
            }
        }

        return array_values(array_unique($nameservers));
    }

    private function resolveTlsDiagnostics(string $host): ?array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $started = microtime(true);
        $client = @stream_socket_client('ssl://'.$host.':443', $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
        $handshakeMs = round((microtime(true) - $started) * 1000, 2);

        if (! is_resource($client)) {
            return [
                'handshake_ms' => $handshakeMs,
                'error' => trim($errstr) !== '' ? $errstr : 'TLS handshake failed.',
                'certificate_days_remaining' => null,
            ];
        }

        $params = stream_context_get_params($client);
        $meta = stream_get_meta_data($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = is_resource($cert) ? @openssl_x509_parse($cert) : false;

        $validTo = is_array($parsed) && isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null;
        $daysRemaining = $validTo ? (int) floor(($validTo - time()) / 86400) : null;
        $issuer = is_array($parsed) ? (array) ($parsed['issuer'] ?? []) : [];
        $subject = is_array($parsed) ? (array) ($parsed['subject'] ?? []) : [];
        $crypto = is_array($meta) ? (array) ($meta['crypto'] ?? []) : [];

        return [
            'handshake_ms' => $handshakeMs,
            'protocol' => $crypto['protocol'] ?? null,
            'cipher_name' => $crypto['cipher_name'] ?? null,
            'issuer_cn' => $issuer['CN'] ?? null,
            'subject_cn' => $subject['CN'] ?? null,
            'valid_to' => $validTo ? Carbon::createFromTimestampUTC($validTo)->toIso8601String() : null,
            'certificate_days_remaining' => $daysRemaining,
            'error' => null,
        ];
    }

    private function fetchSecurityHeaders(string $url): array
    {
        $target = trim($url) !== '' ? $url : 'https://example.com';
        $criticalHeaders = [
            'content-security-policy',
            'strict-transport-security',
            'x-frame-options',
            'x-content-type-options',
            'referrer-policy',
        ];

        try {
            $started = microtime(true);
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'VendorPulse-ExperienceMonitoring/1.0'])
                ->get($target);
            $latencyMs = round((microtime(true) - $started) * 1000, 2);

            $headers = [];
            foreach ($criticalHeaders as $headerName) {
                $value = $response->header($headerName);
                $headers[$headerName] = is_array($value) ? implode(', ', $value) : $value;
            }

            $missing = [];
            foreach ($criticalHeaders as $headerName) {
                if (! isset($headers[$headerName]) || trim((string) $headers[$headerName]) === '') {
                    $missing[] = $headerName;
                }
            }

            return [
                'url' => $target,
                'status_code' => $response->status(),
                'request_latency_ms' => $latencyMs,
                'server' => $response->header('server'),
                'headers' => $headers,
                'missing_critical' => $missing,
            ];
        } catch (\Throwable $e) {
            return [
                'url' => $target,
                'status_code' => null,
                'request_latency_ms' => null,
                'server' => null,
                'headers' => [],
                'missing_critical' => $criticalHeaders,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function latestSpeedtestForHost(string $organizationId, string $host): ?array
    {
        $runs = WebsiteSpeedtestRun::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('tested_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        foreach ($runs as $run) {
            $runHost = $this->extractHost((string) ($run->final_url ?: $run->target_url));
            if ($runHost !== $host) {
                continue;
            }

            return [
                'id' => $run->id,
                'tested_at' => $run->tested_at?->toIso8601String(),
                'status_code' => $run->status_code,
                'success' => (bool) $run->success,
                'error' => $run->error,
                'total_time_ms' => $run->total_time_ms,
                'ttfb_ms' => $run->ttfb_ms,
                'dns_lookup_ms' => $run->dns_lookup_ms,
                'tcp_connect_ms' => $run->tcp_connect_ms,
                'tls_handshake_ms' => $run->tls_handshake_ms,
                'download_speed_kbps' => $run->download_speed_kbps,
                'checked_from' => $run->checked_from,
            ];
        }

        return null;
    }
}
