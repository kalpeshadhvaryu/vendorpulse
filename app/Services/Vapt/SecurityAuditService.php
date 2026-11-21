<?php

namespace App\Services\Vapt;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Phase-B website security audit: configuration, session/auth signals, CSRF defenses,
 * and limited injection-surface heuristics. Not a full offensive pentest.
 */
class SecurityAuditService
{
    private const USER_AGENT = 'VendorPulse-SecurityAudit/1.0 (+authorized web health check)';

    private const CRITICAL_HEADERS = [
        'strict-transport-security',
        'content-security-policy',
        'x-content-type-options',
        'x-frame-options',
        'referrer-policy',
        'permissions-policy',
    ];

    private const SQL_ERROR_SIGNATURES = [
        'sql syntax',
        'mysql_fetch',
        'mysqli_',
        'pg_query',
        'postgresql',
        'ora-01756',
        'sqlite3.',
        'unclosed quotation mark',
        'odbc sql server driver',
        'sqlstate[',
        'you have an error in your sql',
    ];

    /**
     * @return array<string, mixed>
     */
    public function audit(string $targetUrl, string $depth = 'standard'): array
    {
        $url = $this->normalizeUrl($targetUrl);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'https');

        $findings = [];
        $categories = [
            'security_misconfiguration' => ['pass' => 0, 'warning' => 0, 'fail' => 0],
            'broken_authentication' => ['pass' => 0, 'warning' => 0, 'fail' => 0],
            'csrf' => ['pass' => 0, 'warning' => 0, 'fail' => 0],
            'sensitive_data_exposure' => ['pass' => 0, 'warning' => 0, 'fail' => 0],
            'injection' => ['pass' => 0, 'warning' => 0, 'fail' => 0],
            'xss_surface' => ['pass' => 0, 'warning' => 0, 'fail' => 0],
        ];

        $response = $this->fetch($url);
        $finalUrl = $response?->effectiveUri()?->__toString() ?: $url;
        $status = $response?->status();
        $headers = $this->normalizeHeaders($response);
        $body = (string) ($response?->body() ?? '');
        $cookies = $this->parseSetCookies($response);

        // --- HTTPS / transport ---
        if ($scheme !== 'https' && ! str_starts_with($finalUrl, 'https://')) {
            $findings[] = $this->finding(
                'sensitive_data_exposure',
                'HTTPS not enforced',
                'fail',
                'S1',
                'The target was requested without a secure HTTPS final URL.',
                'Force HTTPS at the reverse proxy/app and redirect all HTTP traffic.',
                ['final_url' => $finalUrl]
            );
        } else {
            $findings[] = $this->finding(
                'sensitive_data_exposure',
                'HTTPS in use',
                'pass',
                'S3',
                'Traffic ends on HTTPS.',
                'Keep TLS certificates renewed and HSTS enabled.',
                ['final_url' => $finalUrl]
            );
        }

        $tls = $host !== '' ? $this->tlsSnapshot($host) : null;
        if ($tls !== null) {
            $days = $tls['certificate_days_remaining'] ?? null;
            if (is_int($days) && $days < 14) {
                $findings[] = $this->finding(
                    'security_misconfiguration',
                    'TLS certificate expiring soon',
                    $days < 7 ? 'fail' : 'warning',
                    $days < 7 ? 'S0' : 'S1',
                    "Certificate has {$days} day(s) remaining.",
                    'Renew the certificate and verify the chain on all edge nodes.',
                    $tls
                );
            } else {
                $findings[] = $this->finding(
                    'security_misconfiguration',
                    'TLS certificate validity',
                    'pass',
                    'S3',
                    'Certificate validity looks healthy.',
                    'Continue monitoring expiry.',
                    $tls
                );
            }
        }

        // --- Security headers / misconfiguration ---
        $missing = [];
        foreach (self::CRITICAL_HEADERS as $header) {
            if (! array_key_exists($header, $headers) || trim((string) $headers[$header]) === '') {
                $missing[] = $header;
            }
        }

        if ($missing !== []) {
            $findings[] = $this->finding(
                'security_misconfiguration',
                'Missing security headers',
                in_array('strict-transport-security', $missing, true) || in_array('content-security-policy', $missing, true) ? 'fail' : 'warning',
                in_array('content-security-policy', $missing, true) ? 'S1' : 'S2',
                'One or more recommended browser security headers are missing.',
                'Add HSTS, CSP, X-Content-Type-Options, X-Frame-Options/frame-ancestors, Referrer-Policy, and Permissions-Policy.',
                ['missing' => $missing]
            );
        } else {
            $findings[] = $this->finding(
                'security_misconfiguration',
                'Core security headers present',
                'pass',
                'S3',
                'Critical security headers were detected.',
                'Review CSP tightness periodically.',
                ['headers_checked' => self::CRITICAL_HEADERS]
            );
        }

        foreach (['server', 'x-powered-by'] as $leakHeader) {
            if (! empty($headers[$leakHeader])) {
                $findings[] = $this->finding(
                    'security_misconfiguration',
                    'Technology disclosure via headers',
                    'warning',
                    'S2',
                    "Response discloses {$leakHeader}.",
                    'Remove or genericize Server / X-Powered-By headers at the edge.',
                    [$leakHeader => $headers[$leakHeader]]
                );
            }
        }

        // Common sensitive path probes (existence only).
        foreach ($this->probeSensitivePaths($url) as $probe) {
            $findings[] = $probe;
        }

        // --- Cookies / broken authentication signals ---
        if ($cookies === []) {
            $findings[] = $this->finding(
                'broken_authentication',
                'No Set-Cookie on landing response',
                'pass',
                'S3',
                'No session cookies were issued on the initial response (may be fine for public pages).',
                'Re-test after login if session cookies are issued only post-authentication.',
                []
            );
        } else {
            foreach ($cookies as $cookie) {
                $name = $cookie['name'];
                $issues = [];
                if (! $cookie['secure']) {
                    $issues[] = 'Missing Secure';
                }
                if (! $cookie['httponly'] && $this->looksLikeSessionCookie($name)) {
                    $issues[] = 'Missing HttpOnly on likely session cookie';
                }
                if ($cookie['samesite'] === null || $cookie['samesite'] === '') {
                    $issues[] = 'Missing SameSite';
                } elseif (strtolower($cookie['samesite']) === 'none' && ! $cookie['secure']) {
                    $issues[] = 'SameSite=None without Secure';
                }

                if ($issues !== []) {
                    $findings[] = $this->finding(
                        'broken_authentication',
                        "Cookie hardening: {$name}",
                        $this->looksLikeSessionCookie($name) ? 'fail' : 'warning',
                        $this->looksLikeSessionCookie($name) ? 'S1' : 'S2',
                        'Cookie attributes are weaker than recommended for authenticated sessions.',
                        'Set Secure; HttpOnly for session cookies; SameSite=Lax or Strict (or None+Secure only when required).',
                        ['cookie' => $name, 'issues' => $issues, 'attributes' => $cookie]
                    );
                } else {
                    $findings[] = $this->finding(
                        'broken_authentication',
                        "Cookie hardening: {$name}",
                        'pass',
                        'S3',
                        'Cookie security attributes look reasonable.',
                        'Confirm these flags remain after authentication flows.',
                        ['cookie' => $name]
                    );
                }
            }
        }

        // --- CSRF defenses on forms ---
        $forms = $this->extractForms($body, $finalUrl);
        $stateChanging = array_values(array_filter(
            $forms,
            static fn (array $f): bool => in_array(strtoupper($f['method']), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        ));

        if ($stateChanging === []) {
            $findings[] = $this->finding(
                'csrf',
                'No state-changing HTML forms detected',
                'pass',
                'S3',
                'No POST/PUT/PATCH/DELETE forms found on the landing page.',
                'If the app uses APIs/SPAs, validate CSRF/SameSite protections on those endpoints separately.',
                ['forms_found' => count($forms)]
            );
        } else {
            $missingCsrf = [];
            foreach ($stateChanging as $form) {
                if (! $form['has_csrf_token']) {
                    $missingCsrf[] = $form['action'];
                }
            }
            if ($missingCsrf !== []) {
                $findings[] = $this->finding(
                    'csrf',
                    'Possible missing CSRF token on forms',
                    'warning',
                    'S2',
                    'One or more state-changing forms did not expose a typical CSRF hidden field.',
                    'Ensure anti-CSRF tokens (or equivalent SameSite + custom header defenses) protect mutating requests.',
                    ['forms_without_token' => array_slice($missingCsrf, 0, 10)]
                );
            } else {
                $findings[] = $this->finding(
                    'csrf',
                    'CSRF token fields detected',
                    'pass',
                    'S3',
                    'State-changing forms include a CSRF-like hidden input.',
                    'Verify server-side validation of the token on every mutating request.',
                    ['forms_checked' => count($stateChanging)]
                );
            }
        }

        // --- Injection / XSS surface (safe heuristics) ---
        $queryParams = [];
        parse_str((string) parse_url($finalUrl, PHP_URL_QUERY), $queryParams);
        $inputNames = [];
        foreach ($forms as $form) {
            foreach ($form['inputs'] as $inputName) {
                $inputNames[] = $inputName;
            }
        }
        $inputNames = array_values(array_unique(array_filter($inputNames)));

        if ($queryParams === [] && $inputNames === []) {
            $findings[] = $this->finding(
                'injection',
                'Limited injection surface on landing page',
                'pass',
                'S3',
                'No query parameters or form fields were found on the landing response.',
                'Deep authenticated scans are still recommended for APIs and logged-in workflows.',
                []
            );
        } else {
            $findings[] = $this->finding(
                'injection',
                'Input surface discovered',
                'warning',
                'S2',
                'Query parameters and/or form fields increase injection testing scope.',
                'Review server-side validation/parameterized queries for these inputs.',
                [
                    'query_params' => array_keys($queryParams),
                    'form_fields_sample' => array_slice($inputNames, 0, 25),
                ]
            );
        }

        if ($depth === 'deep') {
            $findings = array_merge($findings, $this->runSafeInjectionProbes($finalUrl, $queryParams, $forms));
        } else {
            $findings[] = $this->finding(
                'xss_surface',
                'Deep injection probes skipped',
                'pass',
                'S3',
                'Standard mode maps surfaces only. Use Deep mode for safe reflected XSS / SQL-error heuristics.',
                'Run Deep audit on staging or maintenance windows for authorized targets.',
                ['depth' => $depth]
            );
        }

        foreach ($findings as $finding) {
            $cat = $finding['category'];
            $findingStatus = $finding['status'];
            if (isset($categories[$cat][$findingStatus])) {
                $categories[$cat][$findingStatus]++;
            }
        }

        $summary = [
            'total_checks' => count($findings),
            'passed' => count(array_filter($findings, static fn ($f) => $f['status'] === 'pass')),
            'warnings' => count(array_filter($findings, static fn ($f) => $f['status'] === 'warning')),
            'failed' => count(array_filter($findings, static fn ($f) => $f['status'] === 'fail')),
        ];

        $riskScore = min(100, ($summary['failed'] * 18) + ($summary['warnings'] * 8));
        $severity = match (true) {
            $summary['failed'] >= 3 || $riskScore >= 70 => 'S0',
            $summary['failed'] >= 1 || $riskScore >= 40 => 'S1',
            $summary['warnings'] >= 2 => 'S2',
            default => 'S3',
        };

        return [
            'target_url' => $url,
            'final_url' => $finalUrl,
            'host' => $host,
            'depth' => $depth,
            'scanned_at' => now()->toIso8601String(),
            'http_status' => $status,
            'severity' => $severity,
            'risk_score' => $riskScore,
            'summary' => $summary,
            'categories' => $categories,
            'transport' => [
                'scheme' => $scheme,
                'tls' => $tls,
            ],
            'headers' => [
                'present' => array_intersect_key($headers, array_flip(self::CRITICAL_HEADERS)),
                'missing_critical' => $missing,
                'server' => $headers['server'] ?? null,
                'x_powered_by' => $headers['x-powered-by'] ?? null,
            ],
            'cookies' => $cookies,
            'forms' => array_map(static function (array $form): array {
                return [
                    'method' => $form['method'],
                    'action' => $form['action'],
                    'has_csrf_token' => $form['has_csrf_token'],
                    'input_count' => count($form['inputs']),
                ];
            }, $forms),
            'findings' => $findings,
            'scope_note' => 'Phase-B audit: configuration, session cookie signals, CSRF form defenses, and optional safe injection heuristics. Does not replace a full manual pentest (authz/IDOR/business logic).',
        ];
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @param  list<array<string, mixed>>  $forms
     * @return list<array<string, mixed>>
     */
    private function runSafeInjectionProbes(string $finalUrl, array $queryParams, array $forms): array
    {
        $findings = [];

        // Reflected XSS heuristic on first query param or synthetic probe param.
        $probeParam = array_key_first($queryParams) ?: 'vp_xss';
        $marker = 'vp'.Str::lower(Str::random(6));
        $xssPayload = '<vp-xss-'.$marker.'>';
        $xssUrl = $this->withQuery($finalUrl, [$probeParam => $xssPayload]);
        $xssResponse = $this->fetch($xssUrl);
        $xssBody = (string) ($xssResponse?->body() ?? '');
        if ($xssBody !== '' && str_contains($xssBody, $xssPayload)) {
            $findings[] = $this->finding(
                'xss_surface',
                'Possible reflected XSS',
                'fail',
                'S1',
                'Unencoded probe marker was reflected in the HTML response.',
                'Encode output contextually and add a strict Content-Security-Policy.',
                ['param' => $probeParam]
            );
        } else {
            $findings[] = $this->finding(
                'xss_surface',
                'Reflected XSS probe',
                'pass',
                'S3',
                'Safe probe marker was not reflected raw in the response body.',
                'Still test authenticated/JS-heavy routes separately.',
                ['param' => $probeParam]
            );
        }

        // SQL error heuristic — single quote only, no destructive payloads.
        $sqlParam = array_key_first($queryParams) ?: 'vp_id';
        $sqlUrl = $this->withQuery($finalUrl, [$sqlParam => "'"]);
        $sqlResponse = $this->fetch($sqlUrl);
        $sqlBody = Str::lower((string) ($sqlResponse?->body() ?? ''));
        $matched = null;
        foreach (self::SQL_ERROR_SIGNATURES as $signature) {
            if ($sqlBody !== '' && str_contains($sqlBody, $signature)) {
                $matched = $signature;
                break;
            }
        }
        if ($matched !== null) {
            $findings[] = $this->finding(
                'injection',
                'Possible SQL injection (error-based heuristic)',
                'fail',
                'S0',
                'Response contained a database error signature after a safe quote probe.',
                'Use parameterized queries/ORM bindings and hide DB errors from clients.',
                ['param' => $sqlParam, 'signature' => $matched]
            );
        } else {
            $findings[] = $this->finding(
                'injection',
                'SQL error-based probe',
                'pass',
                'S3',
                'No common SQL error signatures observed for the safe quote probe.',
                'Boolean/time-based SQLi and authenticated endpoints still need dedicated testing.',
                ['param' => $sqlParam]
            );
        }

        // If login-like forms exist, note broken-auth testing gap (we don't brute force).
        foreach ($forms as $form) {
            $joined = Str::lower(implode(' ', $form['inputs']).' '.$form['action']);
            if (str_contains($joined, 'password') || str_contains($joined, 'login') || str_contains($joined, 'signin')) {
                $findings[] = $this->finding(
                    'broken_authentication',
                    'Login form detected — credential attacks not executed',
                    'warning',
                    'S2',
                    'A login form was found. VendorPulse does not perform password spraying or MFA bypass.',
                    'Manually verify lockout, MFA, reset flows, session fixation, and cookie flags after login.',
                    ['action' => $form['action']]
                );
                break;
            }
        }

        return $findings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function probeSensitivePaths(string $baseUrl): array
    {
        $findings = [];
        $paths = [
            '/.env',
            '/.git/HEAD',
            '/phpinfo.php',
            '/server-status',
            '/actuator/health',
            '/api/swagger',
            '/swagger-ui.html',
            '/storage/logs/laravel.log',
        ];

        foreach ($paths as $path) {
            $probeUrl = $this->joinUrl($baseUrl, $path);
            $response = $this->fetch($probeUrl, 5);
            $status = $response?->status();
            $body = (string) ($response?->body() ?? '');
            $interesting = is_int($status) && $status >= 200 && $status < 400
                && ! $this->looksLikeGenericNotFound($body, $status);

            if ($interesting) {
                $findings[] = $this->finding(
                    'security_misconfiguration',
                    'Sensitive path reachable: '.$path,
                    'fail',
                    'S0',
                    'A commonly sensitive path returned a successful-looking response.',
                    'Block public access to debug/config/VCS/admin endpoints at the web server or WAF.',
                    ['url' => $probeUrl, 'status' => $status]
                );
            }
        }

        if ($findings === []) {
            $findings[] = $this->finding(
                'security_misconfiguration',
                'Common sensitive paths not exposed',
                'pass',
                'S3',
                'Sample sensitive paths did not return obviously public content.',
                'Keep denying .env/.git/debug endpoints at the edge.',
                ['paths_checked' => $paths]
            );
        }

        return $findings;
    }

    /**
     * @return list<array{name:string,secure:bool,httponly:bool,samesite:?string,raw:string}>
     */
    private function parseSetCookies(?Response $response): array
    {
        if ($response === null) {
            return [];
        }

        $headerBag = $response->toPsrResponse()->getHeader('Set-Cookie');
        $cookies = [];
        foreach ($headerBag as $raw) {
            $parts = array_map('trim', explode(';', $raw));
            $nv = array_shift($parts);
            if ($nv === null || ! str_contains($nv, '=')) {
                continue;
            }
            [$name] = explode('=', $nv, 2);
            $attrs = [
                'name' => trim($name),
                'secure' => false,
                'httponly' => false,
                'samesite' => null,
                'raw' => $raw,
            ];
            foreach ($parts as $part) {
                $lower = strtolower($part);
                if ($lower === 'secure') {
                    $attrs['secure'] = true;
                } elseif ($lower === 'httponly') {
                    $attrs['httponly'] = true;
                } elseif (str_starts_with($lower, 'samesite=')) {
                    $attrs['samesite'] = substr($part, 9);
                }
            }
            $cookies[] = $attrs;
        }

        return $cookies;
    }

    /**
     * @return list<array{method:string,action:string,has_csrf_token:bool,inputs:list<string>}>
     */
    private function extractForms(string $html, string $baseUrl): array
    {
        if ($html === '' || ! preg_match_all('/<form\b[^>]*>.*?<\/form>/is', $html, $matches)) {
            return [];
        }

        $forms = [];
        foreach ($matches[0] as $formHtml) {
            $method = 'GET';
            if (preg_match('/\bmethod=["\']([^"\']+)["\']/i', $formHtml, $m)) {
                $method = strtoupper($m[1]);
            }
            $action = $baseUrl;
            if (preg_match('/\baction=["\']([^"\']*)["\']/i', $formHtml, $m)) {
                $action = $this->absolutize($baseUrl, html_entity_decode($m[1]));
            }

            $inputs = [];
            if (preg_match_all('/<input\b[^>]*>/i', $formHtml, $inputTags)) {
                foreach ($inputTags[0] as $tag) {
                    if (preg_match('/\bname=["\']([^"\']+)["\']/i', $tag, $nm)) {
                        $inputs[] = $nm[1];
                    }
                }
            }

            $hasCsrf = false;
            foreach ($inputs as $name) {
                $lower = strtolower($name);
                if (str_contains($lower, 'csrf') || str_contains($lower, '_token') || $lower === 'authenticity_token') {
                    $hasCsrf = true;
                    break;
                }
            }
            if (! $hasCsrf && preg_match('/name=["\'][^"\']*(csrf|_token)[^"\']*["\']/i', $formHtml)) {
                $hasCsrf = true;
            }

            $forms[] = [
                'method' => $method,
                'action' => $action,
                'has_csrf_token' => $hasCsrf,
                'inputs' => $inputs,
            ];
        }

        return $forms;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeHeaders(?Response $response): array
    {
        if ($response === null) {
            return [];
        }

        $out = [];
        foreach ($response->headers() as $name => $values) {
            $out[strtolower((string) $name)] = is_array($values) ? implode(', ', $values) : (string) $values;
        }

        return $out;
    }

    private function fetch(string $url, int $timeout = 15): ?Response
    {
        try {
            return Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            ])
                ->withOptions(['allow_redirects' => ['track_redirects' => true]])
                ->timeout($timeout)
                ->connectTimeout(5)
                ->get($url);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tlsSnapshot(string $host): ?array
    {
        $port = 443;
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($cert === null) {
            return null;
        }
        $parsed = openssl_x509_parse($cert);
        if (! is_array($parsed)) {
            return null;
        }
        $validTo = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null;

        return [
            'host' => $host,
            'valid_to' => $validTo ? date('c', $validTo) : null,
            'certificate_days_remaining' => $validTo ? (int) floor(($validTo - time()) / 86400) : null,
            'issuer' => is_array($parsed['issuer'] ?? null) ? ($parsed['issuer']['O'] ?? json_encode($parsed['issuer'])) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function finding(
        string $category,
        string $title,
        string $status,
        string $severity,
        string $explanation,
        string $suggestion,
        array $evidence,
    ): array {
        return [
            'category' => $category,
            'title' => $title,
            'status' => $status,
            'severity' => $severity,
            'explanation' => $explanation,
            'suggestion' => $suggestion,
            'evidence' => $evidence,
        ];
    }

    private function looksLikeSessionCookie(string $name): bool
    {
        $lower = strtolower($name);

        return str_contains($lower, 'sess')
            || str_contains($lower, 'token')
            || str_contains($lower, 'auth')
            || str_contains($lower, 'login')
            || in_array($lower, ['phpssid', 'phpsessid', 'laravel_session', 'jsessionid', 'sid'], true);
    }

    private function looksLikeGenericNotFound(string $body, int $status): bool
    {
        if (in_array($status, [401, 403, 404], true)) {
            return true;
        }
        $lower = Str::lower($body);

        return str_contains($lower, 'not found') || str_contains($lower, '404');
    }

    private function normalizeUrl(string $target): string
    {
        $target = trim($target);
        if (! preg_match('#^https?://#i', $target)) {
            $target = 'https://'.$target;
        }

        return $target;
    }

    private function joinUrl(string $base, string $path): string
    {
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port.$path;
    }

    private function absolutize(string $base, string $maybeRelative): string
    {
        if ($maybeRelative === '' || $maybeRelative === '#') {
            return $base;
        }
        if (preg_match('#^https?://#i', $maybeRelative)) {
            return $maybeRelative;
        }
        if (str_starts_with($maybeRelative, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$maybeRelative;
        }
        $baseParts = parse_url($base);
        $origin = ($baseParts['scheme'] ?? 'https').'://'.($baseParts['host'] ?? '');
        if (isset($baseParts['port'])) {
            $origin .= ':'.$baseParts['port'];
        }
        if (str_starts_with($maybeRelative, '/')) {
            return $origin.$maybeRelative;
        }
        $dir = rtrim(dirname($baseParts['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$maybeRelative;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function withQuery(string $url, array $params): string
    {
        $parts = parse_url($url) ?: [];
        $existing = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $existing);
        }
        $merged = array_merge($existing, $params);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = http_build_query($merged);

        return $scheme.'://'.$host.$port.$path.($query !== '' ? '?'.$query : '');
    }
}
