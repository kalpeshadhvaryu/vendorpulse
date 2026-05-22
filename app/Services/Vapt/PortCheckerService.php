<?php

namespace App\Services\Vapt;

use Illuminate\Support\Facades\Http;

class PortCheckerService
{
    private const QUICK_PORTS = [
        21, 22, 25, 53, 80, 110, 143, 443, 465, 587, 993, 995, 3306, 5432, 6379, 8080, 8443,
    ];

    private const RISKY_EXPOSED_PORTS = [
        21, 23, 3389, 5900, 1433, 1521, 3306, 5432, 6379, 27017, 9200, 11211,
    ];

    /**
     * @return array{
     *   target:string,
     *   host:string,
     *   mode:string,
     *   scanned_at:string,
     *   timeout_ms:int,
     *   edge_provider:array{name:string,confidence:string,evidence:array<int,string>,note:string},
     *   summary:array{total_ports:int,open_ports:int,closed_ports:int,filtered_ports:int,risky_open_ports:int},
     *   ports:array<int,array{port:int,state:string,latency_ms:float|null,service_hint:string,banner:string|null,risk:string|null,error:string|null}>,
     *   findings:array<int,array{key:string,title:string,status:string,severity:string,explanation:string,suggestion:string,evidence:array<int,string>}>
     * }
     */
    public function scan(string $target, string $mode = 'quick', ?string $customPorts = null, int $timeoutMs = 1500): array
    {
        $host = $this->extractHost($target);

        if ($host === null) {
            return [
                'target' => trim($target),
                'host' => '',
                'mode' => $mode,
                'scanned_at' => now()->toIso8601String(),
                'timeout_ms' => $timeoutMs,
                'edge_provider' => [
                    'name' => 'Unknown',
                    'confidence' => 'low',
                    'evidence' => [],
                    'note' => 'Target is invalid; edge detection skipped.',
                ],
                'summary' => [
                    'total_ports' => 0,
                    'open_ports' => 0,
                    'closed_ports' => 0,
                    'filtered_ports' => 0,
                    'risky_open_ports' => 0,
                ],
                'ports' => [],
                'findings' => [[
                    'key' => 'target-format',
                    'title' => 'Target format',
                    'status' => 'fail',
                    'severity' => 'S1',
                    'explanation' => 'The input is not a valid hostname or IP.',
                    'suggestion' => 'Provide a valid domain/IP such as example.com or 1.2.3.4.',
                    'evidence' => ['Input: '.trim($target)],
                ]],
            ];
        }

        $ports = $mode === 'extended'
            ? $this->parseCustomPorts($customPorts ?? '')
            : self::QUICK_PORTS;

        if ($ports === []) {
            return [
                'target' => trim($target),
                'host' => $host,
                'mode' => $mode,
                'scanned_at' => now()->toIso8601String(),
                'timeout_ms' => $timeoutMs,
                'edge_provider' => [
                    'name' => 'Unknown',
                    'confidence' => 'low',
                    'evidence' => [],
                    'note' => 'No valid ports to scan; edge detection skipped.',
                ],
                'summary' => [
                    'total_ports' => 0,
                    'open_ports' => 0,
                    'closed_ports' => 0,
                    'filtered_ports' => 0,
                    'risky_open_ports' => 0,
                ],
                'ports' => [],
                'findings' => [[
                    'key' => 'custom-ports',
                    'title' => 'Custom ports input',
                    'status' => 'warning',
                    'severity' => 'S2',
                    'explanation' => 'No valid ports were parsed from the custom input.',
                    'suggestion' => 'Use formats like 80,443,8080 or 1-1024 or mixed values.',
                    'evidence' => ['Input: '.trim((string) $customPorts)],
                ]],
            ];
        }

        $results = [];
        foreach ($ports as $port) {
            $results[] = $this->scanPort($host, $port, $timeoutMs);
        }

        $open = array_values(array_filter($results, static fn (array $row): bool => $row['state'] === 'open'));
        $closed = array_values(array_filter($results, static fn (array $row): bool => $row['state'] === 'closed'));
        $filtered = array_values(array_filter($results, static fn (array $row): bool => $row['state'] === 'filtered'));

        $riskyOpen = array_values(array_filter($open, static fn (array $row): bool => $row['risk'] === 'high'));
        $legacyOpen = array_values(array_filter(
            $open,
            static fn (array $row): bool => in_array($row['port'], [21, 23], true),
        ));
        $webOpen = array_values(array_filter(
            $open,
            static fn (array $row): bool => in_array($row['port'], [80, 443, 8080, 8443], true),
        ));

        $edgeProvider = $this->detectEdgeProvider($host);

        $findings = [];

        $findings[] = $this->finding(
            key: 'port-coverage',
            title: 'Port scan coverage',
            status: 'pass',
            severity: 'S4',
            explanation: 'Port scan completed for the selected profile.',
            suggestion: 'Repeat scan from multiple regions to catch firewall or geo-dependent behavior.',
            evidence: [
                'Mode: '.$mode,
                'Total scanned ports: '.count($ports),
                'Open: '.count($open).', Closed: '.count($closed).', Filtered: '.count($filtered),
            ],
        );

        if ($riskyOpen !== []) {
            $findings[] = $this->finding(
                key: 'risky-open-ports',
                title: 'Sensitive ports exposed publicly',
                status: 'fail',
                severity: 'S1',
                explanation: 'One or more high-risk service ports are open and reachable.',
                suggestion: 'Restrict access via firewall/VPN/private network and expose only required public services.',
                evidence: [
                    'Risky open ports: '.implode(', ', array_map(static fn (array $row): string => (string) $row['port'], $riskyOpen)),
                ],
            );
        }

        if ($legacyOpen !== []) {
            $findings[] = $this->finding(
                key: 'legacy-services',
                title: 'Legacy or weak protocol ports open',
                status: 'warning',
                severity: 'S2',
                explanation: 'Legacy service ports (FTP/Telnet) appear reachable.',
                suggestion: 'Replace with secure alternatives (SFTP/SSH) and close insecure legacy ports.',
                evidence: [
                    'Legacy open ports: '.implode(', ', array_map(static fn (array $row): string => (string) $row['port'], $legacyOpen)),
                ],
            );
        }

        if ($webOpen === []) {
            $findings[] = $this->finding(
                key: 'web-entrypoint',
                title: 'No standard web ports open',
                status: 'warning',
                severity: 'S3',
                explanation: 'No open ports found among 80/443/8080/8443.',
                suggestion: 'If this is intended to be a web endpoint, verify DNS target, hosting firewall, and load balancer exposure.',
                evidence: ['Open web ports: none'],
            );
        }

        if (count($open) === 0) {
            $findings[] = $this->finding(
                key: 'all-closed',
                title: 'No open TCP ports detected',
                status: 'warning',
                severity: 'S3',
                explanation: 'All scanned TCP ports are closed or filtered from this scan source.',
                suggestion: 'If services should be public, verify security groups/firewall and run scans from another network.',
                evidence: ['Scan source may be blocked or target is intentionally private.'],
            );
        }

        if ($edgeProvider['name'] !== 'Unknown') {
            $findings[] = $this->finding(
                key: 'edge-provider-detected',
                title: 'Edge / Firewall provider detected',
                status: 'pass',
                severity: 'S4',
                explanation: 'The scanned hostname appears to be served through an edge/CDN/WAF provider.',
                suggestion: 'Interpret filtered/open results as edge behavior first, and scan origin IP separately when authorized.',
                evidence: array_merge([
                    'Provider: '.$edgeProvider['name'],
                    'Confidence: '.$edgeProvider['confidence'],
                ], $edgeProvider['evidence']),
            );
        }

        if ($edgeProvider['name'] !== 'Unknown' && $filtered !== []) {
            $findings[] = $this->finding(
                key: 'edge-filtered-context',
                title: 'Filtered ports likely impacted by edge policy',
                status: 'warning',
                severity: 'S2',
                explanation: 'Filtered TCP results may reflect edge-provider policy rather than origin host state.',
                suggestion: 'If needed, compare scans against authorized origin endpoints and internal network vantage points.',
                evidence: [
                    'Filtered ports: '.implode(', ', array_map(static fn (array $row): string => (string) $row['port'], $filtered)),
                    'Detected provider: '.$edgeProvider['name'],
                ],
            );
        }

        if ($findings === []) {
            $findings[] = $this->finding(
                key: 'baseline',
                title: 'No immediate exposure risk in scanned set',
                status: 'pass',
                severity: 'S4',
                explanation: 'No high-risk exposure pattern was detected in the scanned ports.',
                suggestion: 'Keep periodic scans and review any newly opened ports after infrastructure changes.',
                evidence: ['Open ports: '.implode(', ', array_map(static fn (array $row): string => (string) $row['port'], $open))],
            );
        }

        return [
            'target' => trim($target),
            'host' => $host,
            'mode' => $mode,
            'scanned_at' => now()->toIso8601String(),
            'timeout_ms' => $timeoutMs,
            'edge_provider' => $edgeProvider,
            'summary' => [
                'total_ports' => count($ports),
                'open_ports' => count($open),
                'closed_ports' => count($closed),
                'filtered_ports' => count($filtered),
                'risky_open_ports' => count($riskyOpen),
            ],
            'ports' => $results,
            'findings' => $findings,
        ];
    }

    /**
     * @return array{port:int,state:string,latency_ms:float|null,service_hint:string,banner:string|null,risk:string|null,error:string|null}
     */
    private function scanPort(string $host, int $port, int $timeoutMs): array
    {
        $timeoutSeconds = max(0.3, $timeoutMs / 1000);
        $errno = 0;
        $errstr = '';

        $start = microtime(true);
        $socket = @stream_socket_client(
            'tcp://'.$host.':'.$port,
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
        );
        $latencyMs = round((microtime(true) - $start) * 1000, 2);

        $state = 'closed';
        $banner = null;
        $error = null;

        if (is_resource($socket)) {
            $state = 'open';
            stream_set_timeout($socket, 0, 250000);
            $peek = @fread($socket, 256);
            if (is_string($peek)) {
                $peek = trim($peek);
                $banner = $peek !== '' ? $this->sanitizeUtf8($peek) : null;
            }
            fclose($socket);
        } else {
            $error = trim($errstr) !== '' ? $this->sanitizeUtf8(trim($errstr)) : 'Connection failed';
            if ($errno === 110 || str_contains(strtolower($error), 'timed out')) {
                $state = 'filtered';
            }
        }

        return [
            'port' => $port,
            'state' => $state,
            'latency_ms' => $state === 'open' ? $latencyMs : null,
            'service_hint' => $this->sanitizeUtf8($this->serviceHint($port)),
            'banner' => $banner,
            'risk' => $state === 'open' ? $this->riskLevel($port) : null,
            'error' => $state === 'open' ? null : $error,
        ];
    }

    private function serviceHint(int $port): string
    {
        return match ($port) {
            21 => 'FTP',
            22 => 'SSH',
            23 => 'Telnet',
            25 => 'SMTP',
            53 => 'DNS',
            80 => 'HTTP',
            110 => 'POP3',
            143 => 'IMAP',
            443 => 'HTTPS',
            465 => 'SMTPS',
            587 => 'SMTP Submission',
            993 => 'IMAPS',
            995 => 'POP3S',
            1433 => 'MSSQL',
            1521 => 'Oracle DB',
            3306 => 'MySQL',
            3389 => 'RDP',
            5432 => 'PostgreSQL',
            5900 => 'VNC',
            6379 => 'Redis',
            8080 => 'HTTP Alt',
            8443 => 'HTTPS Alt',
            9200 => 'Elasticsearch',
            default => 'Unknown/Custom',
        };
    }

    private function riskLevel(int $port): string
    {
        if (in_array($port, self::RISKY_EXPOSED_PORTS, true)) {
            return 'high';
        }

        if (in_array($port, [22, 25, 53, 587], true)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @return array<int,int>
     */
    private function parseCustomPorts(string $input): array
    {
        $raw = trim($input);
        if ($raw === '') {
            return [];
        }

        $ports = [];
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^(\d{1,5})\s*-\s*(\d{1,5})$/', $part, $m) === 1) {
                $start = (int) $m[1];
                $end = (int) $m[2];
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }
                $start = max(1, $start);
                $end = min(65535, $end);
                for ($p = $start; $p <= $end; $p++) {
                    $ports[] = $p;
                    if (count($ports) >= 512) {
                        break 2;
                    }
                }
                continue;
            }

            if (preg_match('/^\d{1,5}$/', $part) === 1) {
                $port = (int) $part;
                if ($port >= 1 && $port <= 65535) {
                    $ports[] = $port;
                    if (count($ports) >= 512) {
                        break;
                    }
                }
            }
        }

        $ports = array_values(array_unique($ports));
        sort($ports);

        return $ports;
    }

    /**
     * @return array{key:string,title:string,status:string,severity:string,explanation:string,suggestion:string,evidence:array<int,string>}
     */
    private function finding(
        string $key,
        string $title,
        string $status,
        string $severity,
        string $explanation,
        string $suggestion,
        array $evidence,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'status' => $status,
            'severity' => $severity,
            'explanation' => $explanation,
            'suggestion' => $suggestion,
            'evidence' => array_values($evidence),
        ];
    }

    /**
     * @return array{name:string,confidence:string,evidence:array<int,string>,note:string}
     */
    private function detectEdgeProvider(string $host): array
    {
        $evidence = [];
        $matchedName = null;
        $confidence = 'low';

        $httpSignals = $this->detectProviderFromHttp($host);
        if ($httpSignals['name'] !== null) {
            $matchedName = $httpSignals['name'];
            $confidence = $httpSignals['confidence'];
            $evidence = array_merge($evidence, $httpSignals['evidence']);
        }

        $dnsSignals = $this->detectProviderFromDns($host);
        if ($matchedName === null && $dnsSignals['name'] !== null) {
            $matchedName = $dnsSignals['name'];
            $confidence = $dnsSignals['confidence'];
        }
        $evidence = array_merge($evidence, $dnsSignals['evidence']);

        if ($matchedName === null) {
            return [
                'name' => 'Unknown',
                'confidence' => 'low',
                'evidence' => $evidence,
                'note' => 'No strong edge-provider fingerprint found from DNS/HTTP in this scan.',
            ];
        }

        return [
            'name' => $matchedName,
            'confidence' => $confidence,
            'evidence' => array_values(array_unique($evidence)),
            'note' => 'Best-effort fingerprint; edge provider and origin firewall can differ.',
        ];
    }

    /**
     * @return array{name:string|null,confidence:string,evidence:array<int,string>}
     */
    private function detectProviderFromHttp(string $host): array
    {
        $targets = ['https://'.$host, 'http://'.$host];
        $headerBag = [];

        foreach ($targets as $url) {
            try {
                $response = Http::timeout(6)
                    ->withoutRedirecting()
                    ->withHeaders(['User-Agent' => 'VendorPulse-PortChecker/1.0'])
                    ->get($url);

                foreach ($response->headers() as $key => $values) {
                    $headerBag[strtolower((string) $key)] = strtolower(implode(', ', (array) $values));
                }

                if ($headerBag !== []) {
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($headerBag === []) {
            return ['name' => null, 'confidence' => 'low', 'evidence' => []];
        }

        $known = [
            'Cloudflare' => ['cf-ray', 'cf-cache-status', 'server:cloudflare'],
            'Akamai' => ['x-akamai', 'akamai-origin-hop', 'server:akamai'],
            'Fastly' => ['x-served-by', 'x-fastly', 'via:fastly'],
            'Sucuri' => ['x-sucuri-id', 'x-sucuri-cache'],
            'Imperva' => ['x-iinfo', 'incap-ses', 'visid_incap'],
            'CloudFront' => ['x-amz-cf-id', 'x-amz-cf-pop'],
        ];

        foreach ($known as $provider => $needles) {
            $matches = [];
            foreach ($needles as $needle) {
                if (str_contains($needle, ':')) {
                    [$k, $v] = explode(':', $needle, 2);
                    $headerValue = $headerBag[$k] ?? '';
                    if ($headerValue !== '' && str_contains($headerValue, $v)) {
                        $matches[] = $k.':'.$v;
                    }
                    continue;
                }

                if (array_key_exists($needle, $headerBag)) {
                    $matches[] = $needle;
                }
            }

            if ($matches !== []) {
                return [
                    'name' => $provider,
                    'confidence' => count($matches) >= 2 ? 'high' : 'medium',
                    'evidence' => ['HTTP header fingerprints: '.implode(', ', $matches)],
                ];
            }
        }

        return [
            'name' => null,
            'confidence' => 'low',
            'evidence' => ['HTTP headers collected but no known provider signature matched.'],
        ];
    }

    /**
     * @return array{name:string|null,confidence:string,evidence:array<int,string>}
     */
    private function detectProviderFromDns(string $host): array
    {
        $fqdn = rtrim($host, '.').'.';
        $records = @dns_get_record($fqdn, DNS_A + DNS_AAAA + DNS_CNAME);

        $cnames = [];
        $ips = [];
        foreach ((array) $records as $record) {
            if (! is_array($record)) {
                continue;
            }

            if (($record['type'] ?? null) === 'CNAME' && isset($record['target'])) {
                $cnames[] = strtolower((string) $record['target']);
            }
            if (($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                $ips[] = (string) $record['ip'];
            }
        }

        $cnameJoined = strtolower(implode(' ', $cnames));
        $rdns = [];
        foreach (array_slice(array_values(array_unique($ips)), 0, 4) as $ip) {
            $ptr = @gethostbyaddr($ip);
            if (is_string($ptr) && $ptr !== '' && $ptr !== $ip) {
                $rdns[] = strtolower($ptr);
            }
        }

        $rdnsJoined = implode(' ', $rdns);
        $haystack = $cnameJoined.' '.$rdnsJoined;

        $map = [
            'Cloudflare' => ['cloudflare', 'cdn.cloudflare', '.cloudflare.net'],
            'Akamai' => ['akamai', '.akamaiedge.net', '.edgekey.net'],
            'Fastly' => ['fastly', '.fastly.net'],
            'CloudFront' => ['cloudfront', '.cloudfront.net'],
            'Imperva' => ['imperva', 'incapsula'],
        ];

        foreach ($map as $provider => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, strtolower($needle))) {
                    return [
                        'name' => $provider,
                        'confidence' => 'medium',
                        'evidence' => [
                            'DNS/CNAME/PTR hint: '.$needle,
                            'CNAME chain: '.($cnames !== [] ? implode(', ', $cnames) : 'none'),
                            'PTR: '.($rdns !== [] ? implode(', ', $rdns) : 'none'),
                        ],
                    ];
                }
            }
        }

        return [
            'name' => null,
            'confidence' => 'low',
            'evidence' => [
                'CNAME chain: '.($cnames !== [] ? implode(', ', $cnames) : 'none'),
                'PTR: '.($rdns !== [] ? implode(', ', $rdns) : 'none'),
            ],
        ];
    }

    private function extractHost(string $value): ?string
    {
        $target = strtolower(trim($value));
        if ($target === '') {
            return null;
        }

        if (str_contains($target, '://')) {
            $host = parse_url($target, PHP_URL_HOST);
        } else {
            $candidate = str_contains($target, '/') || str_contains($target, '?') || str_contains($target, '#')
                ? 'https://'.$target
                : null;

            $host = $candidate !== null
                ? parse_url($candidate, PHP_URL_HOST)
                : $target;
        }

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $host = trim($host, '.');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return strtolower($host);
        }

        $isValidDomain = filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        return $isValidDomain ? strtolower($host) : null;
    }

    private function sanitizeUtf8(string $value): string
    {
        $clean = $value;

        if (! preg_match('//u', $clean)) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $clean);
            if ($converted !== false) {
                $clean = $converted;
            } else {
                $clean = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $clean) ?? '';
            }
        }

        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean) ?? '';

        return trim($clean);
    }
}
