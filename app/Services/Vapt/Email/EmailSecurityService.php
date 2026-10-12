<?php

namespace App\Services\Vapt\Email;

use Illuminate\Support\Facades\Http;

class EmailSecurityService
{
    private const SPF_LOOKUP_LIMIT = 10;

    private const SPF_MAX_FETCHES = 25;

    public function __construct(private readonly DnsLookup $dns) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $host, ?string $dkimSelector = null): array
    {
        $domain = $this->emailDomain($host);
        $txt = $this->dns->txt($domain);
        $mxRecords = $this->dns->mx($domain);

        $nullMx = count($mxRecords) === 1 && $mxRecords[0]['host'] === '';
        $receivesMail = $mxRecords !== [] && ! $nullMx;

        $spf = $this->analyzeSpf($txt, $domain);
        $dmarc = $this->analyzeDmarc($domain);
        $dkim = $this->analyzeDkim($domain, $dkimSelector);
        $mxHosts = $receivesMail ? $this->analyzeMxHosts($mxRecords) : [];
        $mtaSts = $this->analyzeMtaSts($domain, $mxHosts);
        $tlsRpt = $this->analyzeTlsRpt($domain);
        $bimi = $this->analyzeBimi($domain);
        $blacklist = $this->analyzeBlacklists($domain, $mxHosts);

        $checks = array_values(array_filter([
            $this->spfCheck($spf),
            $spf['record'] !== null ? $this->spfLookupCheck($spf) : null,
            $this->dmarcCheck($dmarc),
            $this->dkimCheck($dkim),
            $receivesMail ? $this->mxReverseDnsCheck($mxHosts) : null,
            $blacklist['enabled'] ? $this->blacklistCheck($blacklist) : null,
            $receivesMail ? $this->mtaStsCheck($mtaSts) : null,
            $receivesMail ? $this->tlsRptCheck($tlsRpt) : null,
            $this->bimiCheck($bimi, $dmarc),
        ]));

        $breakdown = $this->scoreBreakdown($spf, $dmarc, $dkim, $mxHosts, $blacklist, $mtaSts, $tlsRpt, $bimi, $receivesMail);
        $earned = array_sum(array_column($breakdown, 'earned'));
        $possible = array_sum(array_column($breakdown, 'possible'));
        $score = $possible > 0 ? (int) round($earned / $possible * 100) : 0;

        return [
            'domain' => $domain,
            'score' => $score,
            'grade' => $this->grade($score),
            'breakdown' => $breakdown,
            'receives_mail' => $receivesMail,
            'null_mx' => $nullMx,
            'spf' => $spf,
            'dmarc' => $dmarc,
            'dkim' => $dkim,
            'mx_hosts' => $mxHosts,
            'mta_sts' => $mtaSts,
            'tls_rpt' => $tlsRpt,
            'bimi' => $bimi,
            'blacklist' => $blacklist,
            'checks' => $checks,
        ];
    }

    private function emailDomain(string $host): string
    {
        $domain = strtolower(rtrim(trim($host), '.'));

        return str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
    }

    /**
     * @param  array<int, string>  $txt
     * @return array<string, mixed>
     */
    public function analyzeSpf(array $txt, string $domain = ''): array
    {
        $records = array_values(array_filter(
            $txt,
            static fn (string $row): bool => preg_match('/^v=spf1(\s|$)/i', trim($row)) === 1,
        ));

        $result = [
            'records' => $records,
            'record' => count($records) === 1 ? trim($records[0]) : null,
            'all' => null,
            'lookups' => 0,
            'void_lookups' => 0,
            'includes' => [],
            'uses_ptr' => false,
            'loop' => false,
            'errors' => [],
        ];

        if ($result['record'] === null) {
            return $result;
        }

        $state = ['lookups' => 0, 'void' => 0, 'fetches' => 0, 'cache' => [], 'includes' => [], 'ptr' => false, 'loop' => false, 'errors' => []];
        $this->walkSpf($result['record'], $domain !== '' ? [strtolower($domain)] : [], $state);

        $result['all'] = $this->spfAllQualifier($result['record']);
        $result['lookups'] = $state['lookups'];
        $result['void_lookups'] = $state['void'];
        $result['includes'] = array_values(array_unique($state['includes']));
        $result['uses_ptr'] = $state['ptr'];
        $result['loop'] = $state['loop'];
        $result['errors'] = array_values(array_unique($state['errors']));

        return $result;
    }

    /**
     * @param  array<int, string>  $chain
     * @param  array<string, mixed>  $state
     */
    private function walkSpf(string $record, array $chain, array &$state): void
    {
        if (count($chain) > self::SPF_LOOKUP_LIMIT) {
            $state['errors'][] = 'SPF include chain is nested too deeply.';

            return;
        }

        $terms = preg_split('/\s+/', trim($record)) ?: [];

        foreach (array_slice($terms, 1) as $term) {
            $lower = strtolower($term);
            $mechanism = ltrim($lower, '+-~?');

            $target = null;
            if (str_starts_with($mechanism, 'include:')) {
                $target = substr($mechanism, 8);
            } elseif (str_starts_with($lower, 'redirect=')) {
                $target = substr($lower, 9);
            }

            if ($target !== null) {
                $state['lookups']++;
                $state['includes'][] = $target;
                $this->followSpfTarget($target, $chain, $state);

                continue;
            }

            if (preg_match('/^(a|mx|exists)([:\/]|$)/', $mechanism) === 1) {
                $state['lookups']++;
            } elseif (preg_match('/^ptr([:\/]|$)/', $mechanism) === 1) {
                $state['lookups']++;
                $state['ptr'] = true;
            }
        }
    }

    /**
     * @param  array<int, string>  $chain
     * @param  array<string, mixed>  $state
     */
    private function followSpfTarget(string $target, array $chain, array &$state): void
    {
        if (str_contains($target, '%')) {
            return;
        }

        if (in_array($target, $chain, true)) {
            $state['loop'] = true;
            $state['errors'][] = 'SPF include loop: '.end($chain).' includes '.$target.', which is already being evaluated.';

            return;
        }

        if (! array_key_exists($target, $state['cache'])) {
            if ($state['fetches'] >= self::SPF_MAX_FETCHES) {
                return;
            }

            $state['fetches']++;
            $state['cache'][$target] = array_values(array_filter(
                $this->dns->txt($target),
                static fn (string $row): bool => preg_match('/^v=spf1(\s|$)/i', trim($row)) === 1,
            ))[0] ?? null;
        }

        if ($state['cache'][$target] === null) {
            $state['void']++;
            $state['errors'][] = 'No SPF record found at included domain '.$target.'.';

            return;
        }

        $this->walkSpf($state['cache'][$target], [...$chain, $target], $state);
    }

    private function spfAllQualifier(string $record): ?string
    {
        if (preg_match('/(?:^|\s)([+\-~?]?)all(?:\s|$)/i', $record, $match) === 1) {
            return ($match[1] === '' ? '+' : $match[1]).'all';
        }

        return preg_match('/(?:^|\s)redirect=/i', $record) === 1 ? 'redirect' : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeDmarc(string $domain): array
    {
        $source = $domain;
        $records = $this->dmarcRecordsAt($domain);

        $labels = explode('.', $domain);
        if ($records === [] && count($labels) > 2) {
            $parent = implode('.', array_slice($labels, -2));
            $parentRecords = $this->dmarcRecordsAt($parent);
            if ($parentRecords !== []) {
                $records = $parentRecords;
                $source = $parent;
            }
        }

        $record = count($records) === 1 ? $records[0] : null;

        return [
            'records' => $records,
            'record' => $record,
            'source' => $source,
            'tags' => $record !== null ? $this->parseDmarcTags($record) : [],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function dmarcRecordsAt(string $domain): array
    {
        return array_values(array_filter(
            $this->dns->txt('_dmarc.'.$domain),
            static fn (string $row): bool => preg_match('/^v=DMARC1\s*(;|$)/i', trim($row)) === 1,
        ));
    }

    /**
     * @return array{p:?string, sp:?string, pct:int, rua:array<int,string>, ruf:array<int,string>, adkim:string, aspf:string}
     */
    public function parseDmarcTags(string $record): array
    {
        $tags = $this->parseTagList($record);

        $uris = static fn (?string $value): array => $value === null || trim($value) === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $value))));

        return [
            'p' => isset($tags['p']) ? strtolower($tags['p']) : null,
            'sp' => isset($tags['sp']) ? strtolower($tags['sp']) : null,
            'pct' => isset($tags['pct']) && is_numeric($tags['pct']) ? max(0, min(100, (int) $tags['pct'])) : 100,
            'rua' => $uris($tags['rua'] ?? null),
            'ruf' => $uris($tags['ruf'] ?? null),
            'adkim' => strtolower($tags['adkim'] ?? 'r') === 's' ? 's' : 'r',
            'aspf' => strtolower($tags['aspf'] ?? 'r') === 's' ? 's' : 'r',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeDkim(string $domain, ?string $customSelector): array
    {
        $customSelector = $customSelector !== null ? strtolower(trim($customSelector)) : null;
        if ($customSelector === '') {
            $customSelector = null;
        }

        $selectors = (array) config('email-security.dkim_selectors', []);
        if ($customSelector !== null) {
            array_unshift($selectors, $customSelector);
        }
        $selectors = array_values(array_unique(array_map('strtolower', $selectors)));

        // A wildcard *._domainkey record answers every selector; detect it with a name nobody uses.
        $wildcard = $this->dkimRecord('vp-probe-'.bin2hex(random_bytes(4)).'._domainkey.'.$domain);

        $keys = [];
        foreach ($selectors as $selector) {
            $record = $this->dkimRecord($selector.'._domainkey.'.$domain);
            if ($record !== null && $record !== $wildcard) {
                $keys[] = $this->parseDkimKey($selector, $record);
            }
        }

        $foundSelectors = array_column($keys, 'selector');

        if ($wildcard !== null) {
            $keys[] = $this->parseDkimKey('*', $wildcard);
        }

        return [
            'domain' => $domain,
            'wildcard' => $wildcard !== null,
            'selectors_checked' => count($selectors),
            'custom_selector' => $customSelector,
            'custom_selector_found' => $customSelector !== null && in_array($customSelector, $foundSelectors, true),
            'keys' => $keys,
        ];
    }

    private function dkimRecord(string $name): ?string
    {
        foreach ($this->dns->txt($name) as $record) {
            if (preg_match('/^v=spf1/i', trim($record)) !== 1 && preg_match('/(^|;)\s*p=/i', $record) === 1) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @return array{selector:string, record:string, key_type:string, key_bits:?int, testing:bool, revoked:bool, issues:array<int,string>}
     */
    public function parseDkimKey(string $selector, string $record): array
    {
        $tags = $this->parseTagList($record);
        $keyType = strtolower($tags['k'] ?? 'rsa');
        $publicKey = preg_replace('/\s+/', '', (string) ($tags['p'] ?? '')) ?? '';
        $revoked = $publicKey === '';
        $testing = in_array('y', array_map('trim', explode(':', strtolower($tags['t'] ?? ''))), true);

        $bits = null;
        if (! $revoked) {
            $bits = $keyType === 'ed25519' ? 256 : $this->rsaKeyBits($publicKey);
        }

        $issues = [];
        if ($revoked) {
            $issues[] = 'Key is revoked (empty p= value).';
        }
        if ($testing) {
            $issues[] = 'Testing mode (t=y): receivers may ignore DKIM failures.';
        }
        if ($keyType === 'rsa' && $bits !== null && $bits < 1024) {
            $issues[] = 'RSA key is weaker than 1024 bits and is rejected by major mailbox providers.';
        } elseif ($keyType === 'rsa' && $bits !== null && $bits < 2048) {
            $issues[] = 'RSA key is 1024 bits; 2048 bits is recommended.';
        }
        if (! $revoked && $keyType === 'rsa' && $bits === null) {
            $issues[] = 'Public key could not be parsed.';
        }
        if (! in_array($keyType, ['rsa', 'ed25519'], true)) {
            $issues[] = 'Unknown key type k='.$keyType.'.';
        }

        return [
            'selector' => $selector,
            'record' => $record,
            'key_type' => $keyType,
            'key_bits' => $bits,
            'testing' => $testing,
            'revoked' => $revoked,
            'issues' => $issues,
        ];
    }

    private function rsaKeyBits(string $base64Key): ?int
    {
        if (! function_exists('openssl_pkey_get_public') || base64_decode($base64Key, true) === false) {
            return null;
        }

        $body = chunk_split($base64Key, 64, "\n");

        foreach (['PUBLIC KEY', 'RSA PUBLIC KEY'] as $label) {
            $key = @openssl_pkey_get_public("-----BEGIN {$label}-----\n{$body}-----END {$label}-----\n");
            if ($key !== false) {
                $details = openssl_pkey_get_details($key);
                if (is_array($details) && isset($details['bits'])) {
                    return (int) $details['bits'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{host:string, priority:int}>  $mxRecords
     * @return array<int, array{host:string, priority:int, ips:array<int, array{ip:string, public:bool, ptr:array<int,string>, fcrdns:bool}>}>
     */
    private function analyzeMxHosts(array $mxRecords): array
    {
        $hosts = [];

        foreach (array_slice($mxRecords, 0, max(1, (int) config('email-security.max_mx_hosts', 5))) as $mx) {
            $ips = [];
            foreach (array_slice($this->dns->a($mx['host']), 0, 2) as $ip) {
                $ptr = $this->dns->ptr($ip);
                $fcrdns = false;
                foreach ($ptr as $ptrHost) {
                    if (in_array($ip, $this->dns->a($ptrHost), true)) {
                        $fcrdns = true;
                        break;
                    }
                }

                $ips[] = [
                    'ip' => $ip,
                    'public' => $this->isPublicIp($ip),
                    'ptr' => $ptr,
                    'fcrdns' => $fcrdns,
                ];
            }

            $hosts[] = [
                'host' => $mx['host'],
                'priority' => $mx['priority'],
                'ips' => $ips,
            ];
        }

        return $hosts;
    }

    /**
     * @param  array<int, array{host:string, priority:int, ips:array<int, array<string, mixed>>}>  $mxHosts
     * @return array<string, mixed>
     */
    private function analyzeMtaSts(string $domain, array $mxHosts): array
    {
        $records = array_values(array_filter(
            $this->dns->txt('_mta-sts.'.$domain),
            static fn (string $row): bool => preg_match('/^v=STSv1\s*(;|$)/i', trim($row)) === 1,
        ));

        $result = [
            'record' => $records[0] ?? null,
            'policy_url' => 'https://mta-sts.'.$domain.'/.well-known/mta-sts.txt',
            'policy' => null,
            'error' => null,
            'unmatched_mx' => [],
        ];

        if ($result['record'] === null) {
            return $result;
        }

        $policyHostIps = $this->dns->a('mta-sts.'.$domain);
        if ($policyHostIps === [] || array_filter($policyHostIps, fn (string $ip): bool => ! $this->isPublicIp($ip)) !== []) {
            $result['error'] = 'Policy host mta-sts.'.$domain.' does not resolve to a public IP.';

            return $result;
        }

        try {
            $response = Http::timeout((int) config('email-security.http_timeout_seconds', 5))
                ->withOptions(['allow_redirects' => false])
                ->get($result['policy_url']);

            if (! $response->ok()) {
                $result['error'] = 'Policy file returned HTTP '.$response->status().'.';

                return $result;
            }

            $result['policy'] = $this->parseMtaStsPolicy(substr($response->body(), 0, 65536));
        } catch (\Throwable $e) {
            $result['error'] = 'Policy file could not be fetched: '.$e->getMessage();

            return $result;
        }

        if ($result['policy']['mode'] === null) {
            $result['error'] = 'Policy file is missing the mode field.';

            return $result;
        }

        foreach ($mxHosts as $mx) {
            if (! $this->mxMatchesPolicy($mx['host'], $result['policy']['mx'])) {
                $result['unmatched_mx'][] = $mx['host'];
            }
        }

        return $result;
    }

    /**
     * @return array{version:?string, mode:?string, mx:array<int,string>, max_age:?int}
     */
    public function parseMtaStsPolicy(string $body): array
    {
        $policy = ['version' => null, 'mode' => null, 'mx' => [], 'max_age' => null];

        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);

            match ($key) {
                'version' => $policy['version'] = $value,
                'mode' => $policy['mode'] = strtolower($value),
                'mx' => $policy['mx'][] = strtolower(rtrim($value, '.')),
                'max_age' => $policy['max_age'] = is_numeric($value) ? (int) $value : null,
                default => null,
            };
        }

        return $policy;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    public function mxMatchesPolicy(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $host) {
                return true;
            }

            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1);
                $prefix = substr($host, 0, -strlen($suffix));
                if (str_ends_with($host, $suffix) && $prefix !== '' && ! str_contains($prefix, '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{record:?string, rua:array<int,string>}
     */
    private function analyzeTlsRpt(string $domain): array
    {
        $records = array_values(array_filter(
            $this->dns->txt('_smtp._tls.'.$domain),
            static fn (string $row): bool => preg_match('/^v=TLSRPTv1\s*(;|$)/i', trim($row)) === 1,
        ));

        $record = $records[0] ?? null;
        $tags = $record !== null ? $this->parseTagList($record) : [];

        return [
            'record' => $record,
            'rua' => isset($tags['rua']) ? array_values(array_filter(array_map('trim', explode(',', $tags['rua'])))) : [],
        ];
    }

    /**
     * @return array{record:?string, logo:?string, vmc:?string}
     */
    private function analyzeBimi(string $domain): array
    {
        $records = array_values(array_filter(
            $this->dns->txt('default._bimi.'.$domain),
            static fn (string $row): bool => preg_match('/^v=BIMI1\s*(;|$)/i', trim($row)) === 1,
        ));

        $record = $records[0] ?? null;
        $tags = $record !== null ? $this->parseTagList($record) : [];

        return [
            'record' => $record,
            'logo' => ($tags['l'] ?? '') !== '' ? $tags['l'] : null,
            'vmc' => ($tags['a'] ?? '') !== '' ? $tags['a'] : null,
        ];
    }

    /**
     * @param  array<int, array{host:string, priority:int, ips:array<int, array{ip:string, public:bool, ptr:array<int,string>, fcrdns:bool}>}>  $mxHosts
     * @return array<string, mixed>
     */
    private function analyzeBlacklists(string $domain, array $mxHosts): array
    {
        $ips = [];
        foreach ($mxHosts as $mx) {
            foreach ($mx['ips'] as $row) {
                if ($row['public']) {
                    $ips[] = $row['ip'];
                }
            }
        }
        $ips = array_slice(array_values(array_unique($ips)), 0, max(1, (int) config('email-security.blacklist.max_ips', 2)));

        return $this->checkBlacklists($ips, $domain);
    }

    /**
     * @param  array<int, string>  $ips  public IPv4 addresses
     * @return array{enabled:bool, checked:int, listed:int, errors:int, results:array<int, array{target:string, list:string, status:string, response:?string}>}
     */
    public function checkBlacklists(array $ips, ?string $domain): array
    {
        $enabled = (bool) config('email-security.blacklist.enabled', true);
        $result = ['enabled' => $enabled, 'checked' => 0, 'listed' => 0, 'errors' => 0, 'results' => []];

        if (! $enabled) {
            return $result;
        }

        $dqsKey = trim((string) config('email-security.blacklist.spamhaus_dqs_key', ''));
        $testEntries = (array) config('email-security.blacklist.domain_zone_test_entries', []);
        $ipZones = $this->withDqs((array) config('email-security.blacklist.ip_zones', []), $dqsKey, []);
        $domainZones = $domain !== null && $domain !== ''
            ? $this->withDqs((array) config('email-security.blacklist.domain_zones', []), $dqsKey, $testEntries)
            : [];

        if ($ips !== []) {
            foreach ($ipZones as $zone) {
                $reachable = $this->zoneAnswersTestEntry('2.0.0.127.'.$zone['query']);
                foreach ($ips as $ip) {
                    $reversed = implode('.', array_reverse(explode('.', $ip)));
                    $result['results'][] = $reachable
                        ? $this->queryBlacklist($ip, $reversed.'.'.$zone['query'], $zone['label'])
                        : $this->unverifiedBlacklist($ip, $zone['label']);
                }
            }
        }

        foreach ($domainZones as $zone) {
            $reachable = $zone['test'] === null || $this->zoneAnswersTestEntry($zone['test'].'.'.$zone['query']);
            $result['results'][] = $reachable
                ? $this->queryBlacklist($domain, $domain.'.'.$zone['query'], $zone['label'])
                : $this->unverifiedBlacklist($domain, $zone['label']);
        }

        $statuses = array_column($result['results'], 'status');
        $result['checked'] = count($statuses);
        $result['listed'] = count(array_keys($statuses, 'listed', true));
        $result['errors'] = count(array_keys($statuses, 'error', true));

        return $result;
    }

    /**
     * @param  array<string, string>  $zones
     * @param  array<string, string>  $testEntries
     * @return array<int, array{query:string, label:string, test:?string}>
     */
    private function withDqs(array $zones, string $dqsKey, array $testEntries): array
    {
        $mapped = [];
        foreach ($zones as $zone => $label) {
            $query = $zone;
            if ($dqsKey !== '') {
                $query = match ($zone) {
                    'zen.spamhaus.org' => $dqsKey.'.zen.dq.spamhaus.net',
                    'dbl.spamhaus.org' => $dqsKey.'.dbl.dq.spamhaus.net',
                    default => $zone,
                };
            }

            $mapped[] = ['query' => $query, 'label' => $label, 'test' => $testEntries[$zone] ?? null];
        }

        return $mapped;
    }

    private function zoneAnswersTestEntry(string $query): bool
    {
        foreach ($this->dns->a($query) as $answer) {
            if ($this->isListingResponse($answer)) {
                return true;
            }
        }

        return false;
    }

    private function isListingResponse(string $response): bool
    {
        return str_starts_with($response, '127.')
            && $response !== '127.0.0.1'
            && ! str_starts_with($response, '127.255.255.');
    }

    /**
     * @return array{target:string, list:string, status:string, response:?string}
     */
    private function unverifiedBlacklist(string $target, string $label): array
    {
        return ['target' => $target, 'list' => $label, 'status' => 'error', 'response' => 'list unreachable from this resolver'];
    }

    /**
     * @return array{target:string, list:string, status:string, response:?string}
     */
    private function queryBlacklist(string $target, string $query, string $label): array
    {
        $answers = $this->dns->a($query);

        if ($answers === []) {
            return ['target' => $target, 'list' => $label, 'status' => 'clean', 'response' => null];
        }

        return [
            'target' => $target,
            'list' => $label,
            'status' => $this->isListingResponse($answers[0]) ? 'listed' : 'error',
            'response' => $answers[0],
        ];
    }

    /**
     * @param  array<string, mixed>  $spf
     * @return array<string, mixed>
     */
    private function spfCheck(array $spf): array
    {
        $count = count($spf['records']);
        $all = $spf['all'];

        if ($count === 0) {
            return $this->result('spf', 'SPF policy', 'warning', 'S2',
                'No SPF record was found, so receivers cannot tell which servers may send mail for this domain.',
                'Publish one TXT record starting with v=spf1 that lists your mail providers and ends with ~all or -all.',
                ['SPF records: none']);
        }

        if ($count > 1) {
            return $this->result('spf', 'SPF policy', 'fail', 'S1',
                'Multiple SPF records were found. Receivers treat this as a permanent error and SPF fails for every message.',
                'Merge all SPF records into exactly one v=spf1 TXT record.',
                array_map(static fn (string $r): string => 'SPF: '.$r, $spf['records']));
        }

        $evidence = ['SPF: '.$spf['record'], 'Default policy: '.($all ?? 'missing')];

        if ($all === '+all') {
            return $this->result('spf', 'SPF policy', 'fail', 'S1',
                'SPF ends with +all, which authorizes every server on the internet to send as this domain.',
                'Replace +all with -all (or ~all while testing).', $evidence);
        }

        if ($all === null || $all === '?all') {
            return $this->result('spf', 'SPF policy', 'warning', 'S2',
                $all === null
                    ? 'SPF has no "all" mechanism, so unlisted senders get a neutral result.'
                    : 'SPF ends with ?all (neutral), which gives no protection against spoofing.',
                'End the SPF record with ~all (soft fail) or -all (hard fail).', $evidence);
        }

        if ($spf['uses_ptr']) {
            return $this->result('spf', 'SPF policy', 'warning', 'S3',
                'SPF uses the deprecated ptr mechanism, which is slow and ignored by some receivers.',
                'Replace ptr with explicit ip4/ip6 or include mechanisms.', $evidence);
        }

        return $this->result('spf', 'SPF policy', 'pass', 'S4',
            'A single SPF policy was found with an enforcing default ('.$all.').',
            'Review SPF whenever you add or remove an email provider.', $evidence);
    }

    /**
     * @param  array<string, mixed>  $spf
     * @return array<string, mixed>
     */
    private function spfLookupCheck(array $spf): array
    {
        $lookups = (int) $spf['lookups'];
        $evidence = array_merge(
            ['DNS lookups: '.$lookups.' / '.self::SPF_LOOKUP_LIMIT, 'Includes: '.($spf['includes'] !== [] ? implode(', ', $spf['includes']) : 'none')],
            array_map(static fn (string $e): string => 'Error: '.$e, $spf['errors']),
        );

        if ($spf['loop']) {
            return $this->result('spf-lookups', 'SPF DNS lookup limit', 'fail', 'S1',
                'SPF includes itself (directly or through another include). Receivers follow the loop until the lookup limit and SPF fails with a permanent error.',
                'Remove the include that points back to this domain and list its mechanisms directly instead.', $evidence);
        }

        if ($lookups > self::SPF_LOOKUP_LIMIT) {
            return $this->result('spf-lookups', 'SPF DNS lookup limit', 'fail', 'S1',
                'SPF needs '.$lookups.' DNS lookups. Above 10, receivers return a permanent error and SPF fails.',
                'Remove unused includes or replace them with ip4/ip6 ranges (SPF flattening).', $evidence);
        }

        if ($spf['void_lookups'] > 0 || $lookups >= 9) {
            return $this->result('spf-lookups', 'SPF DNS lookup limit', 'warning', 'S3',
                $spf['void_lookups'] > 0
                    ? 'One or more SPF includes point to domains without an SPF record.'
                    : 'SPF is close to the 10-lookup limit; adding one more provider may break it.',
                'Clean up broken includes and keep total lookups well under 10.', $evidence);
        }

        return $this->result('spf-lookups', 'SPF DNS lookup limit', 'pass', 'S4',
            'SPF uses '.$lookups.' of the 10 allowed DNS lookups.',
            'Re-check this count after adding new email providers.', $evidence);
    }

    /**
     * @param  array<string, mixed>  $dmarc
     * @return array<string, mixed>
     */
    private function dmarcCheck(array $dmarc): array
    {
        if ($dmarc['records'] === []) {
            return $this->result('dmarc', 'DMARC policy', 'warning', 'S2',
                'No DMARC record was found, so spoofed mail using this domain is not blocked and you receive no reports.',
                'Publish _dmarc TXT "v=DMARC1; p=none; rua=mailto:dmarc@yourdomain" and move to quarantine/reject after reviewing reports.',
                ['DMARC: none']);
        }

        if (count($dmarc['records']) > 1) {
            return $this->result('dmarc', 'DMARC policy', 'fail', 'S1',
                'Multiple DMARC records were found; receivers ignore DMARC entirely in this case.',
                'Keep exactly one v=DMARC1 TXT record at _dmarc.',
                array_map(static fn (string $r): string => 'DMARC: '.$r, $dmarc['records']));
        }

        $tags = $dmarc['tags'];
        $evidence = [
            'DMARC: '.$dmarc['record'],
            'Found at: _dmarc.'.$dmarc['source'],
            'Policy: '.($tags['p'] ?? 'missing').' · Subdomains: '.($tags['sp'] ?? 'same').' · pct: '.$tags['pct'],
            'Alignment: DKIM '.($tags['adkim'] === 's' ? 'strict' : 'relaxed').', SPF '.($tags['aspf'] === 's' ? 'strict' : 'relaxed'),
            'Aggregate reports (rua): '.($tags['rua'] !== [] ? implode(', ', $tags['rua']) : 'none'),
        ];

        if (! in_array($tags['p'], ['none', 'quarantine', 'reject'], true)) {
            return $this->result('dmarc', 'DMARC policy', 'fail', 'S1',
                'DMARC record has a missing or invalid p= policy, so receivers ignore it.',
                'Set p=none, p=quarantine or p=reject.', $evidence);
        }

        if ($tags['p'] === 'none') {
            return $this->result('dmarc', 'DMARC policy', 'warning', 'S3',
                'DMARC is in monitoring mode (p=none): spoofed mail is still delivered.',
                $tags['rua'] === []
                    ? 'Add rua= to receive reports, then move to p=quarantine and p=reject.'
                    : 'Review the aggregate reports, then move to p=quarantine and p=reject.',
                $evidence);
        }

        if ($tags['pct'] < 100 || $tags['sp'] === 'none') {
            return $this->result('dmarc', 'DMARC policy', 'warning', 'S3',
                $tags['pct'] < 100
                    ? 'DMARC enforcement applies to only '.$tags['pct'].'% of failing mail.'
                    : 'DMARC is enforced on the main domain but subdomains are set to sp=none.',
                'Raise pct to 100 and make sp= at least quarantine.', $evidence);
        }

        return $this->result('dmarc', 'DMARC policy', 'pass', 'S4',
            'DMARC is enforced (p='.$tags['p'].').',
            $tags['rua'] === []
                ? 'Add rua= so you receive reports about who sends mail as your domain.'
                : 'Keep reviewing aggregate reports for new or failing senders.',
            $evidence);
    }

    /**
     * @param  array<string, mixed>  $dkim
     * @return array<string, mixed>
     */
    private function dkimCheck(array $dkim): array
    {
        $keys = $dkim['keys'];
        $evidence = ['Selectors checked: '.$dkim['selectors_checked']];
        $active = array_values(array_filter($keys, static fn (array $k): bool => ! $k['revoked']));

        if ($dkim['custom_selector'] !== null) {
            $evidence[] = 'Your selector "'.$dkim['custom_selector'].'": '.($dkim['custom_selector_found'] ? 'found' : 'not found');

            if (! $dkim['custom_selector_found'] && $active === []) {
                return $this->result('dkim', 'DKIM signing keys', 'fail', 'S1',
                    'No DKIM key is published at '.$dkim['custom_selector'].'._domainkey.'.$dkim['domain'].', and none was found on common selector names.',
                    'Check the selector spelling. If it is right, publish the DKIM record your mail provider gives you at that name, then check again.',
                    $evidence);
            }
        }

        foreach ($keys as $key) {
            $evidence[] = $key['selector'].': '.strtoupper($key['key_type'])
                .($key['key_bits'] !== null ? ' '.$key['key_bits'].'-bit' : '')
                .($key['issues'] !== [] ? ' ('.implode(' ', $key['issues']).')' : '');
        }

        if ($keys === []) {
            return $this->result('dkim', 'DKIM signing keys', 'warning', 'S2',
                'No DKIM key was found on '.$dkim['selectors_checked'].' common selector names. DNS cannot list DKIM keys, so your provider may use a name we did not try.',
                'Send a test email with the Email deliverability test (it reads your selector automatically), or use "Find my DKIM key" in the Email security section. If DKIM is not set up, enable it in your mail provider.',
                $evidence);
        }

        if ($active === []) {
            if ($dkim['wildcard'] ?? false) {
                return $this->result('dkim', 'DKIM signing keys', 'warning', 'S2',
                    'The domain publishes an empty wildcard DKIM key (*._domainkey), which tells receivers it does not sign email. That is correct for a domain that never sends email.',
                    'If this domain does send email, publish your provider\'s DKIM key at its selector; a specific selector overrides the wildcard.',
                    $evidence);
            }

            return $this->result('dkim', 'DKIM signing keys', 'warning', 'S2',
                'Only revoked DKIM keys were found.',
                'Publish an active DKIM key for the selector your mail server signs with.', $evidence);
        }

        $strongest = max(array_map(static fn (array $k): int => $k['key_type'] === 'ed25519' ? 4096 : (int) ($k['key_bits'] ?? 0), $active));

        if ($strongest > 0 && $strongest < 1024) {
            return $this->result('dkim', 'DKIM signing keys', 'fail', 'S1',
                'DKIM keys are weaker than 1024 bits and are treated as invalid by Gmail, Outlook and Yahoo.',
                'Rotate to a 2048-bit RSA key.', $evidence);
        }

        if ($strongest > 0 && $strongest < 2048) {
            return $this->result('dkim', 'DKIM signing keys', 'warning', 'S3',
                'DKIM is published but uses 1024-bit RSA keys.',
                'Rotate to a 2048-bit RSA key when your provider supports it.', $evidence);
        }

        $testing = array_filter($active, static fn (array $k): bool => $k['testing']);
        if (count($testing) === count($active)) {
            return $this->result('dkim', 'DKIM signing keys', 'warning', 'S3',
                'DKIM keys are in testing mode (t=y).',
                'Remove t=y once signing is confirmed working.', $evidence);
        }

        return $this->result('dkim', 'DKIM signing keys', 'pass', 'S4',
            count($active).' active DKIM key'.(count($active) === 1 ? '' : 's').' found.',
            'Rotate DKIM keys periodically and remove keys for providers you no longer use.', $evidence);
    }

    /**
     * @param  array<int, array{host:string, priority:int, ips:array<int, array{ip:string, public:bool, ptr:array<int,string>, fcrdns:bool}>}>  $mxHosts
     * @return array<string, mixed>
     */
    private function mxReverseDnsCheck(array $mxHosts): array
    {
        $total = 0;
        $missingPtr = [];
        $noFcrdns = [];
        $evidence = [];

        foreach ($mxHosts as $mx) {
            foreach ($mx['ips'] as $row) {
                $total++;
                $evidence[] = $mx['host'].' '.$row['ip'].' → '.($row['ptr'] !== [] ? implode(', ', $row['ptr']) : 'no PTR')
                    .($row['ptr'] !== [] && ! $row['fcrdns'] ? ' (does not resolve back)' : '');
                if ($row['ptr'] === []) {
                    $missingPtr[] = $row['ip'];
                } elseif (! $row['fcrdns']) {
                    $noFcrdns[] = $row['ip'];
                }
            }
        }

        if ($total === 0) {
            return $this->result('mx-reverse-dns', 'Mail server reverse DNS', 'fail', 'S1',
                'MX hosts do not resolve to any IPv4 address, so mail cannot be delivered to this domain.',
                'Fix the A records of the MX hostnames.', ['MX hosts: '.implode(', ', array_column($mxHosts, 'host'))]);
        }

        if ($missingPtr !== []) {
            return $this->result('mx-reverse-dns', 'Mail server reverse DNS', 'warning', 'S2',
                'Some mail server IPs have no reverse DNS (PTR) record. Many receivers reject mail from servers without one.',
                'Ask the server/IP provider to set a PTR record matching the mail hostname.', $evidence);
        }

        if ($noFcrdns !== []) {
            return $this->result('mx-reverse-dns', 'Mail server reverse DNS', 'warning', 'S3',
                'Some PTR hostnames do not resolve back to the same IP (forward-confirmed reverse DNS fails).',
                'Make the PTR hostname have an A record pointing to the same IP.', $evidence);
        }

        return $this->result('mx-reverse-dns', 'Mail server reverse DNS', 'pass', 'S4',
            'All mail server IPs have matching forward and reverse DNS.',
            'Keep PTR records updated when moving mail servers.', $evidence);
    }

    /**
     * @param  array<string, mixed>  $blacklist
     * @return array<string, mixed>
     */
    private function blacklistCheck(array $blacklist): array
    {
        $listed = array_values(array_filter($blacklist['results'], static fn (array $r): bool => $r['status'] === 'listed'));
        $evidence = array_map(
            static fn (array $r): string => $r['target'].' on '.$r['list'].': '.$r['status'].($r['response'] !== null ? ' ('.$r['response'].')' : ''),
            $listed !== [] ? $listed : array_values(array_filter($blacklist['results'], static fn (array $r): bool => $r['status'] === 'error')),
        );
        array_unshift($evidence, 'Lookups: '.$blacklist['checked'].' · Listed: '.$blacklist['listed'].' · Unverified: '.$blacklist['errors']);

        if ($listed !== []) {
            return $this->result('email-blacklist', 'Blacklist status', 'fail', 'S1',
                'The domain or a mail server IP is listed on '.count($listed).' blacklist'.(count($listed) === 1 ? '' : 's').'. Mail may be rejected or sent to spam.',
                'Find and fix the cause (compromised account, open relay, bulk mail), then request delisting from each list.',
                $evidence);
        }

        if ($blacklist['checked'] === 0 || $blacklist['errors'] === $blacklist['checked']) {
            return $this->result('email-blacklist', 'Blacklist status', 'warning', 'S3',
                'Blacklist status could not be verified; the lists refused queries from this resolver.',
                'Run the check from a server with its own DNS resolver, or configure a Spamhaus DQS key.', $evidence);
        }

        return $this->result('email-blacklist', 'Blacklist status', 'pass', 'S4',
            'Neither the domain nor its mail server IPs are on the checked blacklists.',
            'Keep monitoring; listings can appear after a single compromised mailbox.', $evidence);
    }

    /**
     * @param  array<string, mixed>  $mtaSts
     * @return array<string, mixed>
     */
    private function mtaStsCheck(array $mtaSts): array
    {
        if ($mtaSts['record'] === null) {
            return $this->result('mta-sts', 'MTA-STS (enforced TLS)', 'warning', 'S3',
                'MTA-STS is not configured, so other servers may deliver mail to you without encryption if TLS is stripped.',
                'Publish _mta-sts TXT "v=STSv1; id=1" and host the policy at '.$mtaSts['policy_url'].'.',
                ['MTA-STS: none']);
        }

        $evidence = ['TXT: '.$mtaSts['record'], 'Policy: '.$mtaSts['policy_url']];

        if ($mtaSts['error'] !== null) {
            return $this->result('mta-sts', 'MTA-STS (enforced TLS)', 'fail', 'S2',
                'An MTA-STS record exists but the policy file is not usable: '.$mtaSts['error'],
                'Serve the policy over HTTPS with a valid certificate at '.$mtaSts['policy_url'].'.', $evidence);
        }

        $policy = $mtaSts['policy'];
        $evidence[] = 'Mode: '.$policy['mode'].' · max_age: '.($policy['max_age'] ?? 'n/a');
        $evidence[] = 'Policy MX: '.($policy['mx'] !== [] ? implode(', ', $policy['mx']) : 'none');

        if ($mtaSts['unmatched_mx'] !== []) {
            return $this->result('mta-sts', 'MTA-STS (enforced TLS)', $policy['mode'] === 'enforce' ? 'fail' : 'warning', $policy['mode'] === 'enforce' ? 'S1' : 'S2',
                'MX hosts not covered by the MTA-STS policy: '.implode(', ', $mtaSts['unmatched_mx']).'.'
                    .($policy['mode'] === 'enforce' ? ' Senders will refuse to deliver to them.' : ''),
                'Add every MX hostname (or a *.domain pattern) to the mx: lines of the policy.', $evidence);
        }

        if ($policy['mode'] !== 'enforce') {
            return $this->result('mta-sts', 'MTA-STS (enforced TLS)', 'warning', 'S4',
                'MTA-STS is in '.$policy['mode'].' mode; TLS is not yet enforced.',
                'Switch mode to enforce after confirming TLS-RPT reports are clean.', $evidence);
        }

        return $this->result('mta-sts', 'MTA-STS (enforced TLS)', 'pass', 'S4',
            'MTA-STS is enforced; senders must use valid TLS when delivering mail.',
            'Bump the id= value in the TXT record whenever the policy changes.', $evidence);
    }

    /**
     * @param  array{record:?string, rua:array<int,string>}  $tlsRpt
     * @return array<string, mixed>
     */
    private function tlsRptCheck(array $tlsRpt): array
    {
        if ($tlsRpt['record'] === null) {
            return $this->result('tls-rpt', 'TLS reporting (TLS-RPT)', 'warning', 'S4',
                'TLS-RPT is not configured, so you are not told when other servers fail to deliver to you securely.',
                'Publish _smtp._tls TXT "v=TLSRPTv1; rua=mailto:tls-reports@yourdomain".',
                ['TLS-RPT: none']);
        }

        return $this->result('tls-rpt', 'TLS reporting (TLS-RPT)', 'pass', 'S4',
            'TLS-RPT is configured.',
            'Review the daily TLS reports for delivery failures.',
            ['TLS-RPT: '.$tlsRpt['record']]);
    }

    /**
     * @param  array{record:?string, logo:?string, vmc:?string}  $bimi
     * @param  array<string, mixed>  $dmarc
     * @return array<string, mixed>
     */
    private function bimiCheck(array $bimi, array $dmarc): array
    {
        $enforced = in_array($dmarc['tags']['p'] ?? null, ['quarantine', 'reject'], true);

        if ($bimi['record'] === null) {
            return $this->result('bimi', 'BIMI brand logo', 'warning', 'S4',
                'BIMI is not configured (optional). It shows your logo next to messages in Gmail, Yahoo and Apple Mail.',
                $enforced
                    ? 'Publish default._bimi TXT with an SVG logo URL (l=) and, for Gmail, a VMC certificate (a=).'
                    : 'First enforce DMARC (quarantine/reject); BIMI requires it.',
                ['BIMI: none']);
        }

        $evidence = ['BIMI: '.$bimi['record'], 'Logo: '.($bimi['logo'] ?? 'none'), 'VMC: '.($bimi['vmc'] ?? 'none')];

        if (! $enforced) {
            return $this->result('bimi', 'BIMI brand logo', 'warning', 'S3',
                'BIMI is published but DMARC is not enforced, so mailbox providers will not show the logo.',
                'Move DMARC to p=quarantine or p=reject.', $evidence);
        }

        return $this->result('bimi', 'BIMI brand logo', 'pass', 'S4',
            'BIMI is published with DMARC enforcement.',
            $bimi['vmc'] === null ? 'Add a VMC certificate (a=) so Gmail displays the logo.' : 'Keep the VMC certificate renewed.',
            $evidence);
    }

    /**
     * @return array<int, array{key:string, label:string, earned:int, possible:int}>
     */
    private function scoreBreakdown(
        array $spf,
        array $dmarc,
        array $dkim,
        array $mxHosts,
        array $blacklist,
        array $mtaSts,
        array $tlsRpt,
        array $bimi,
        bool $receivesMail,
    ): array {
        $rows = [];

        $spfPoints = 0;
        if ($spf['record'] !== null && $spf['all'] !== '+all') {
            $spfPoints = in_array($spf['all'], ['-all', '~all', 'redirect'], true) ? 20 : 10;
            if ($spf['loop'] || $spf['lookups'] > self::SPF_LOOKUP_LIMIT) {
                $spfPoints = 5;
            }
        }
        $rows[] = ['key' => 'spf', 'label' => 'SPF', 'earned' => $spfPoints, 'possible' => 20];

        $tags = $dmarc['tags'];
        $dmarcPoints = 0;
        if (count($dmarc['records']) === 1) {
            $dmarcPoints = match ($tags['p'] ?? null) {
                'reject' => 25,
                'quarantine' => 22,
                'none' => 10,
                default => 0,
            };
            if ($dmarcPoints > 10 && $tags['pct'] < 100) {
                $dmarcPoints -= 5;
            }
            if ($dmarcPoints > 0 && $tags['rua'] === []) {
                $dmarcPoints -= 3;
            }
        }
        $rows[] = ['key' => 'dmarc', 'label' => 'DMARC', 'earned' => max(0, $dmarcPoints), 'possible' => 25];

        $dkimPoints = 0;
        foreach ($dkim['keys'] as $key) {
            if ($key['revoked']) {
                continue;
            }
            $bits = $key['key_type'] === 'ed25519' ? 4096 : (int) ($key['key_bits'] ?? 2048);
            $dkimPoints = max($dkimPoints, $bits >= 2048 ? 20 : ($bits >= 1024 ? 15 : 5));
        }
        $rows[] = ['key' => 'dkim', 'label' => 'DKIM', 'earned' => $dkimPoints, 'possible' => 20];

        if ($blacklist['enabled'] && $blacklist['checked'] > 0) {
            $points = $blacklist['listed'] > 0 ? 0 : ($blacklist['errors'] === $blacklist['checked'] ? 10 : 15);
            $rows[] = ['key' => 'blacklist', 'label' => 'Blacklists', 'earned' => $points, 'possible' => 15];
        }

        if ($receivesMail) {
            $ips = [];
            foreach ($mxHosts as $mx) {
                array_push($ips, ...$mx['ips']);
            }
            $good = count(array_filter($ips, static fn (array $r): bool => $r['fcrdns']));
            $rdnsPoints = $ips === [] ? 0 : (int) round($good / count($ips) * 10);
            $rows[] = ['key' => 'reverse_dns', 'label' => 'Reverse DNS', 'earned' => $rdnsPoints, 'possible' => 10];

            $stsPoints = 0;
            if ($mtaSts['record'] !== null && $mtaSts['error'] === null && $mtaSts['unmatched_mx'] === []) {
                $stsPoints = ($mtaSts['policy']['mode'] ?? null) === 'enforce' ? 5 : 3;
            }
            $rows[] = ['key' => 'mta_sts', 'label' => 'MTA-STS', 'earned' => $stsPoints, 'possible' => 5];
            $rows[] = ['key' => 'tls_rpt', 'label' => 'TLS-RPT', 'earned' => $tlsRpt['record'] !== null ? 3 : 0, 'possible' => 3];
        }

        $rows[] = ['key' => 'bimi', 'label' => 'BIMI', 'earned' => $bimi['record'] !== null ? 2 : 0, 'possible' => 2];

        return $rows;
    }

    public function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 65 => 'C',
            $score >= 50 => 'D',
            default => 'F',
        };
    }

    /**
     * @return array<string, string>
     */
    private function parseTagList(string $record): array
    {
        $tags = [];

        foreach (explode(';', $record) as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $part, 2);
            $key = strtolower(trim($key));
            if ($key !== '') {
                $tags[$key] = trim($value);
            }
        }

        return $tags;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param  array<int, string>  $evidence
     * @return array{key:string, title:string, status:string, severity:string, explanation:string, suggestion:string, evidence:array<int,string>}
     */
    private function result(string $key, string $title, string $status, string $severity, string $explanation, string $suggestion, array $evidence): array
    {
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
}
