<?php

namespace App\Services\Vapt;

use Illuminate\Support\Facades\Http;

class DnsCheckService
{
    /**
     * @return array{
     *   target:string,
     *   host:string,
     *   scanned_at:string,
     *   resolver_nameservers:array<int,string>,
     *   summary:array{total_checks:int,passed:int,warnings:int,failed:int},
     *   records:array<string,mixed>,
     *   checks:array<int,array{key:string,title:string,status:string,severity:string,explanation:string,suggestion:string,evidence:array<int,string>}>
     * }
     */
    public function scan(string $target): array
    {
        $host = $this->extractHost($target);

        if ($host === null) {
            return [
                'target' => trim($target),
                'host' => '',
                'scanned_at' => now()->toIso8601String(),
                'resolver_nameservers' => $this->resolverNameservers(),
                'summary' => [
                    'total_checks' => 1,
                    'passed' => 0,
                    'warnings' => 0,
                    'failed' => 1,
                ],
                'records' => [
                    'a' => [],
                    'aaaa' => [],
                    'ns' => [],
                    'mx' => [],
                    'txt' => [],
                    'soa' => [],
                    'cname' => [],
                    'caa' => [],
                    'dmarc' => [],
                    'dnskey' => [],
                ],
                'checks' => [[
                    'key' => 'target-format',
                    'title' => 'Target format',
                    'status' => 'fail',
                    'severity' => 'S1',
                    'explanation' => 'The input is not a valid hostname or URL host.',
                    'suggestion' => 'Provide a valid domain like example.com or URL like https://example.com.',
                    'evidence' => ['Input: '.trim($target)],
                ]],
            ];
        }

        $fqdn = rtrim($host, '.').'.';

        $resolutionStarted = microtime(true);
        $ipRecords = @dns_get_record($fqdn, DNS_A + DNS_AAAA);
        $lookupLatencyMs = round((microtime(true) - $resolutionStarted) * 1000, 2);

        $nsRecords = @dns_get_record($fqdn, DNS_NS);
        $mxRecords = @dns_get_record($fqdn, DNS_MX);
        $txtRecords = @dns_get_record($fqdn, DNS_TXT);
        $soaRecords = @dns_get_record($fqdn, DNS_SOA);
        $cnameRecords = @dns_get_record($fqdn, DNS_CNAME);
        $caaRecords = defined('DNS_CAA') ? @dns_get_record($fqdn, DNS_CAA) : [];
        $dnskeyRecords = defined('DNS_DNSKEY') ? @dns_get_record($fqdn, DNS_DNSKEY) : [];
        $dmarcRecords = @dns_get_record('_dmarc.'.$fqdn, DNS_TXT);
        $wwwRecords = @dns_get_record('www.'.$fqdn, DNS_A + DNS_AAAA + DNS_CNAME);

        $parentDelegation = $this->fetchParentDelegationNameservers($host);
        $parentNs = $parentDelegation['nameservers'];

        $a = [];
        $aaaa = [];
        $ns = [];
        $mx = [];
        $txt = [];
        $dmarc = [];
        $cname = [];
        $caa = [];
        $dnskey = [];
        $ttlValues = [];
        $wwwA = [];
        $wwwAaaa = [];
        $wwwCname = [];

        foreach ((array) $ipRecords as $record) {
            if (! is_array($record)) {
                continue;
            }

            $ttl = isset($record['ttl']) && is_numeric($record['ttl']) ? (int) $record['ttl'] : null;
            if ($ttl !== null) {
                $ttlValues[] = $ttl;
            }

            if (($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                $a[] = (string) $record['ip'];
            }

            if (($record['type'] ?? null) === 'AAAA' && isset($record['ipv6'])) {
                $aaaa[] = (string) $record['ipv6'];
            }
        }

        foreach ((array) $nsRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            if (isset($record['target'])) {
                $ns[] = strtolower((string) $record['target']);
            }
            if (isset($record['ttl']) && is_numeric($record['ttl'])) {
                $ttlValues[] = (int) $record['ttl'];
            }
        }

        foreach ((array) $mxRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            if (isset($record['target'])) {
                $mx[] = strtolower((string) $record['target']).' (priority '.(int) ($record['pri'] ?? 0).')';
            }
            if (isset($record['ttl']) && is_numeric($record['ttl'])) {
                $ttlValues[] = (int) $record['ttl'];
            }
        }

        foreach ((array) $txtRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            $value = (string) ($record['txt'] ?? '');
            if ($value !== '') {
                $txt[] = $value;
            }
            if (isset($record['ttl']) && is_numeric($record['ttl'])) {
                $ttlValues[] = (int) $record['ttl'];
            }
        }

        foreach ((array) $dmarcRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            $value = (string) ($record['txt'] ?? '');
            if ($value !== '') {
                $dmarc[] = $value;
            }
        }

        foreach ((array) $cnameRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            if (isset($record['target'])) {
                $cname[] = strtolower((string) $record['target']);
            }
        }

        foreach ((array) $caaRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            if (isset($record['tag'], $record['value'])) {
                $caa[] = (string) $record['tag'].' '.(string) $record['value'];
            }
        }

        foreach ((array) $dnskeyRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            if (isset($record['flags'], $record['algorithm'])) {
                $dnskey[] = 'flags='.(string) $record['flags'].', algo='.(string) $record['algorithm'];
            }
        }

        foreach ((array) $wwwRecords as $record) {
            if (! is_array($record)) {
                continue;
            }

            if (($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                $wwwA[] = (string) $record['ip'];
            }

            if (($record['type'] ?? null) === 'AAAA' && isset($record['ipv6'])) {
                $wwwAaaa[] = (string) $record['ipv6'];
            }

            if (($record['type'] ?? null) === 'CNAME' && isset($record['target'])) {
                $wwwCname[] = strtolower((string) $record['target']);
            }
        }

        $soa = [];
        foreach ((array) $soaRecords as $record) {
            if (! is_array($record)) {
                continue;
            }
            if (isset($record['mname'])) {
                $soa[] = strtolower((string) $record['mname']);
            }
        }

        $checks = [];

        $zoneNsNormalized = $this->normalizeHostList($ns);
        $parentNsNormalized = $this->normalizeHostList($parentNs);
        $onlyParent = array_values(array_diff($parentNsNormalized, $zoneNsNormalized));
        $onlyZone = array_values(array_diff($zoneNsNormalized, $parentNsNormalized));

        $checks[] = $this->result(
            key: 'parent-delegation',
            title: 'Parent delegation nameservers',
            status: $parentNsNormalized !== [] ? 'pass' : 'warning',
            severity: $parentNsNormalized !== [] ? 'S4' : 'S2',
            explanation: $parentNsNormalized !== []
                ? 'Registry/parent delegation returned nameservers for this domain.'
                : 'Parent delegation nameservers could not be fetched from RDAP in this run.',
            suggestion: $parentNsNormalized !== []
                ? 'Keep parent delegation nameservers in sync with your authoritative DNS provider.'
                : 'Verify registrar delegation and retry; network restrictions can also block RDAP lookup.',
            evidence: [
                'RDAP source: '.($parentDelegation['source'] ?? 'unknown'),
                'Parent NS: '.($parentNsNormalized !== [] ? implode(', ', $parentNsNormalized) : 'none'),
                'RDAP error: '.($parentDelegation['error'] ?? 'none'),
            ],
        );

        $agreementStatus = 'pass';
        $agreementSeverity = 'S4';
        $agreementExplanation = 'Parent and zone nameserver sets are aligned.';
        $agreementSuggestion = 'Continue maintaining identical NS lists at registrar and DNS zone.';

        if ($parentNsNormalized === []) {
            $agreementStatus = 'warning';
            $agreementSeverity = 'S2';
            $agreementExplanation = 'Unable to evaluate parent vs zone agreement because parent delegation data is unavailable.';
            $agreementSuggestion = 'Re-run when RDAP is reachable, or validate parent NS directly from registry tools.';
        } elseif ($zoneNsNormalized === []) {
            $agreementStatus = 'fail';
            $agreementSeverity = 'S1';
            $agreementExplanation = 'Parent nameservers exist, but no NS records were returned from zone lookups.';
            $agreementSuggestion = 'Fix zone authority responses and ensure NS records are served by delegated nameservers.';
        } elseif ($onlyParent !== [] || $onlyZone !== []) {
            $agreementStatus = 'fail';
            $agreementSeverity = 'S1';
            $agreementExplanation = 'Parent and zone nameserver sets do not match.';
            $agreementSuggestion = 'Make registrar delegation and zone NS records identical to avoid intermittent resolution failures.';
        }

        $checks[] = $this->result(
            key: 'parent-zone-ns-agreement',
            title: 'Parent vs zone NS agreement',
            status: $agreementStatus,
            severity: $agreementSeverity,
            explanation: $agreementExplanation,
            suggestion: $agreementSuggestion,
            evidence: [
                'Parent-only NS: '.($onlyParent !== [] ? implode(', ', $onlyParent) : 'none'),
                'Zone-only NS: '.($onlyZone !== [] ? implode(', ', $onlyZone) : 'none'),
            ],
        );

        $nsResolution = $this->resolveNameserverHosts($parentNsNormalized !== [] ? $parentNsNormalized : $zoneNsNormalized);
        $unresolvedNs = array_keys(array_filter($nsResolution, static fn (array $item): bool => $item['a'] === [] && $item['aaaa'] === []));
        $resolvedNsCount = count($nsResolution) - count($unresolvedNs);

        $checks[] = $this->result(
            key: 'nameserver-host-resolvability',
            title: 'Nameserver host resolvability',
            status: $nsResolution === []
                ? 'warning'
                : (count($unresolvedNs) === 0 ? 'pass' : ($resolvedNsCount > 0 ? 'warning' : 'fail')),
            severity: $nsResolution === []
                ? 'S2'
                : (count($unresolvedNs) === 0 ? 'S4' : ($resolvedNsCount > 0 ? 'S2' : 'S1')),
            explanation: $nsResolution === []
                ? 'No nameserver hosts were available for resolvability testing.'
                : (count($unresolvedNs) === 0
                    ? 'All nameserver hostnames resolve to at least one IP.'
                    : ($resolvedNsCount > 0
                        ? 'Some nameserver hostnames do not resolve to IP addresses.'
                        : 'None of the nameserver hostnames resolved to A/AAAA records.')),
            suggestion: $nsResolution === []
                ? 'Confirm parent delegation and zone NS records first.'
                : (count($unresolvedNs) === 0
                    ? 'Keep glue and host records synchronized during DNS provider changes.'
                    : 'Publish and verify A/AAAA (and glue if in-bailiwick) for unresolved nameserver hosts.'),
            evidence: [
                'Resolved NS hosts: '.$resolvedNsCount,
                'Unresolved NS hosts: '.(count($unresolvedNs) > 0 ? implode(', ', $unresolvedNs) : 'none'),
            ],
        );

        $checks[] = $this->result(
            key: 'dns-resolution',
            title: 'A / AAAA resolution',
            status: ($a !== [] || $aaaa !== []) ? 'pass' : 'fail',
            severity: ($a !== [] || $aaaa !== []) ? 'S4' : 'S1',
            explanation: ($a !== [] || $aaaa !== [])
                ? 'The hostname resolves to at least one IPv4 or IPv6 address.'
                : 'No A or AAAA record was returned for the hostname.',
            suggestion: ($a !== [] || $aaaa !== [])
                ? 'Keep both IPv4 and IPv6 records aligned with your active infrastructure.'
                : 'Add correct A/AAAA records at your DNS provider and re-test propagation.',
            evidence: [
                'A: '.($a !== [] ? implode(', ', $a) : 'none'),
                'AAAA: '.($aaaa !== [] ? implode(', ', $aaaa) : 'none'),
            ],
        );

        $nsCount = count(array_unique($ns));
        $checks[] = $this->result(
            key: 'nameserver-health',
            title: 'Nameserver coverage',
            status: $nsCount >= 2 ? 'pass' : ($nsCount === 1 ? 'warning' : 'fail'),
            severity: $nsCount >= 2 ? 'S4' : ($nsCount === 1 ? 'S2' : 'S1'),
            explanation: $nsCount >= 2
                ? 'Multiple authoritative nameservers are configured.'
                : ($nsCount === 1
                    ? 'Only one nameserver was found, which reduces DNS fault tolerance.'
                    : 'No NS records were found for the hostname.'),
            suggestion: $nsCount >= 2
                ? 'Ensure NS records are hosted on independent infrastructure/providers when possible.'
                : 'Configure at least two authoritative nameservers to improve resiliency.',
            evidence: ['NS count: '.$nsCount, 'NS: '.($ns !== [] ? implode(', ', $ns) : 'none')],
        );

        $checks[] = $this->result(
            key: 'mx-presence',
            title: 'MX record posture',
            status: $mx !== [] ? 'pass' : 'warning',
            severity: $mx !== [] ? 'S4' : 'S3',
            explanation: $mx !== []
                ? 'Mail exchange records are present for inbound email routing.'
                : 'No MX records were found. This may be valid if the domain does not receive mail.',
            suggestion: $mx !== []
                ? 'Verify MX priorities and failover periodically.'
                : 'Add MX records if the domain is used for inbound email.',
            evidence: ['MX: '.($mx !== [] ? implode('; ', $mx) : 'none')],
        );

        $checks[] = $this->result(
            key: 'soa-presence',
            title: 'SOA record presence',
            status: $soa !== [] ? 'pass' : 'fail',
            severity: $soa !== [] ? 'S4' : 'S1',
            explanation: $soa !== []
                ? 'SOA record is present for the zone.'
                : 'No SOA record was returned for this domain.',
            suggestion: $soa !== []
                ? 'Ensure SOA serial updates reflect zone changes consistently across nameservers.'
                : 'Restore authoritative SOA responses from delegated nameservers.',
            evidence: ['SOA MNAME: '.($soa !== [] ? implode(', ', $soa) : 'none')],
        );

        $wwwIps = array_merge($wwwA, $wwwAaaa);
        $hasNonPublicWwwIp = false;
        foreach ($wwwIps as $ip) {
            if (! $this->isPublicIp($ip)) {
                $hasNonPublicWwwIp = true;
                break;
            }
        }

        $wwwStatus = 'pass';
        $wwwSeverity = 'S4';
        $wwwExplanation = 'www hostname resolves correctly.';
        $wwwSuggestion = 'Keep www and apex routing strategy documented and monitored.';

        if ($wwwA === [] && $wwwAaaa === [] && $wwwCname === []) {
            $wwwStatus = 'fail';
            $wwwSeverity = 'S2';
            $wwwExplanation = 'No A, AAAA, or CNAME record was found for www host.';
            $wwwSuggestion = 'Add a www A/AAAA or CNAME record if your web traffic expects www domain access.';
        } elseif ($hasNonPublicWwwIp) {
            $wwwStatus = 'warning';
            $wwwSeverity = 'S1';
            $wwwExplanation = 'At least one www IP is not globally routable.';
            $wwwSuggestion = 'Replace private/reserved www IP records with public service endpoints.';
        }

        $checks[] = $this->result(
            key: 'www-record',
            title: 'WWW record resolvability',
            status: $wwwStatus,
            severity: $wwwSeverity,
            explanation: $wwwExplanation,
            suggestion: $wwwSuggestion,
            evidence: [
                'WWW A: '.($wwwA !== [] ? implode(', ', $wwwA) : 'none'),
                'WWW AAAA: '.($wwwAaaa !== [] ? implode(', ', $wwwAaaa) : 'none'),
                'WWW CNAME: '.($wwwCname !== [] ? implode(', ', $wwwCname) : 'none'),
            ],
        );

        $spfRecords = array_values(array_filter($txt, static fn (string $row): bool => str_starts_with(strtolower($row), 'v=spf1')));
        $checks[] = $this->result(
            key: 'spf',
            title: 'SPF policy',
            status: $spfRecords === [] ? 'warning' : (count($spfRecords) > 1 ? 'fail' : 'pass'),
            severity: $spfRecords === [] ? 'S2' : (count($spfRecords) > 1 ? 'S1' : 'S4'),
            explanation: $spfRecords === []
                ? 'No SPF TXT record was detected.'
                : (count($spfRecords) > 1
                    ? 'Multiple SPF records were found, which can break SPF evaluation.'
                    : 'A single SPF policy was found.'),
            suggestion: $spfRecords === []
                ? 'Publish one SPF TXT record to define authorized sending hosts.'
                : (count($spfRecords) > 1
                    ? 'Merge SPF policies into exactly one v=spf1 TXT record.'
                    : 'Keep SPF includes and mechanisms minimal and reviewed.'),
            evidence: ['SPF records: '.($spfRecords !== [] ? implode(' | ', $spfRecords) : 'none')],
        );

        $dmarcRecord = $dmarc[0] ?? null;
        $hasRejectOrQuarantine = $dmarcRecord !== null
            && (str_contains(strtolower($dmarcRecord), 'p=reject') || str_contains(strtolower($dmarcRecord), 'p=quarantine'));
        $checks[] = $this->result(
            key: 'dmarc',
            title: 'DMARC enforcement',
            status: $dmarcRecord === null ? 'warning' : ($hasRejectOrQuarantine ? 'pass' : 'warning'),
            severity: $dmarcRecord === null ? 'S2' : ($hasRejectOrQuarantine ? 'S4' : 'S3'),
            explanation: $dmarcRecord === null
                ? 'No DMARC record was found at _dmarc.'
                : ($hasRejectOrQuarantine
                    ? 'DMARC is present with enforcement policy (quarantine/reject).'
                    : 'DMARC exists but policy appears monitoring-only (p=none).'),
            suggestion: $dmarcRecord === null
                ? 'Publish a DMARC record and start with aggregate reports, then move to enforcement.'
                : ($hasRejectOrQuarantine
                    ? 'Keep DMARC rua/ruf reporting active and monitor alignment failures.'
                    : 'Move DMARC policy from p=none toward quarantine/reject after validation.'),
            evidence: ['DMARC: '.($dmarcRecord ?? 'none')],
        );

        $checks[] = $this->result(
            key: 'caa',
            title: 'CAA certificate issuance policy',
            status: $caa !== [] ? 'pass' : 'warning',
            severity: $caa !== [] ? 'S4' : 'S3',
            explanation: $caa !== []
                ? 'CAA records are present to constrain certificate issuance.'
                : 'No CAA records were found. Any publicly trusted CA may issue certificates.',
            suggestion: $caa !== []
                ? 'Review allowed CAs and keep issuewild/iodef policies current.'
                : 'Add CAA records to restrict certificate issuance to approved CAs.',
            evidence: ['CAA: '.($caa !== [] ? implode('; ', $caa) : 'none')],
        );

        $minTtl = $ttlValues !== [] ? min($ttlValues) : null;
        $maxTtl = $ttlValues !== [] ? max($ttlValues) : null;
        $ttlStatus = 'pass';
        $ttlSeverity = 'S4';
        $ttlExplanation = 'TTL values are in a generally healthy range.';
        $ttlSuggestion = 'Keep TTL strategy aligned to change frequency and failover needs.';

        if ($minTtl === null) {
            $ttlStatus = 'warning';
            $ttlSeverity = 'S3';
            $ttlExplanation = 'No TTL values were captured from returned records.';
            $ttlSuggestion = 'Verify DNS records are publicly queryable and include standard TTL fields.';
        } elseif ($minTtl < 60) {
            $ttlStatus = 'fail';
            $ttlSeverity = 'S1';
            $ttlExplanation = 'Very low TTL can increase resolver load and query cost.';
            $ttlSuggestion = 'Increase critical record TTLs to at least 300 seconds unless rapid failover is required.';
        } elseif ($minTtl < 300 || ($maxTtl !== null && $maxTtl > 172800)) {
            $ttlStatus = 'warning';
            $ttlSeverity = 'S2';
            $ttlExplanation = 'TTL distribution is aggressive or very long for at least one record.';
            $ttlSuggestion = 'Balance TTL values (commonly 300-86400 seconds) for reliability and propagation control.';
        }

        $checks[] = $this->result(
            key: 'ttl-hygiene',
            title: 'TTL hygiene',
            status: $ttlStatus,
            severity: $ttlSeverity,
            explanation: $ttlExplanation,
            suggestion: $ttlSuggestion,
            evidence: [
                'TTL min: '.($minTtl !== null ? (string) $minTtl : 'n/a'),
                'TTL max: '.($maxTtl !== null ? (string) $maxTtl : 'n/a'),
            ],
        );

        $checks[] = $this->result(
            key: 'lookup-latency',
            title: 'DNS lookup latency',
            status: $lookupLatencyMs <= 120 ? 'pass' : ($lookupLatencyMs <= 300 ? 'warning' : 'fail'),
            severity: $lookupLatencyMs <= 120 ? 'S4' : ($lookupLatencyMs <= 300 ? 'S2' : 'S1'),
            explanation: $lookupLatencyMs <= 120
                ? 'DNS lookup latency is within a healthy threshold.'
                : ($lookupLatencyMs <= 300
                    ? 'DNS lookup latency is elevated and may impact perceived performance.'
                    : 'DNS lookup latency is high and can degrade request startup time.'),
            suggestion: $lookupLatencyMs <= 120
                ? 'Continue monitoring latency trends by region.'
                : 'Review DNS provider performance, resolver path, and record complexity.',
            evidence: ['Lookup latency: '.$lookupLatencyMs.' ms'],
        );

        $hasConflictingCname = $cname !== [] && ($a !== [] || $aaaa !== [] || $mx !== [] || $ns !== []);
        $checks[] = $this->result(
            key: 'cname-consistency',
            title: 'CNAME consistency',
            status: $hasConflictingCname ? 'fail' : 'pass',
            severity: $hasConflictingCname ? 'S1' : 'S4',
            explanation: $hasConflictingCname
                ? 'CNAME appears alongside other record types on the same hostname, which violates DNS standards.'
                : 'No conflicting CNAME usage was detected for this hostname.',
            suggestion: $hasConflictingCname
                ? 'Use either CNAME or other records at a label, not both.'
                : 'Keep CNAME usage limited to non-apex aliases where applicable.',
            evidence: [
                'CNAME: '.($cname !== [] ? implode(', ', $cname) : 'none'),
                'Other records on same label: '.(($a !== [] || $aaaa !== [] || $mx !== [] || $ns !== []) ? 'yes' : 'no'),
            ],
        );

        $checks[] = $this->result(
            key: 'dnssec',
            title: 'DNSSEC signal',
            status: $dnskey !== [] ? 'pass' : 'warning',
            severity: $dnskey !== [] ? 'S4' : 'S3',
            explanation: $dnskey !== []
                ? 'DNSKEY records were detected, indicating DNSSEC is likely configured.'
                : 'No DNSKEY records were detected from this resolver perspective.',
            suggestion: $dnskey !== []
                ? 'Validate DS chain at registrar and monitor RRSIG expiry routinely.'
                : 'Enable DNSSEC at your DNS provider and publish DS at your registrar.',
            evidence: ['DNSKEY entries: '.count($dnskey)],
        );

        $resolverConsensus = $this->collectResolverConsensus($host);
        $consensusMatches = $resolverConsensus['local_vs_google_match'] && $resolverConsensus['local_vs_cloudflare_match'];
        $checks[] = $this->result(
            key: 'resolver-consensus',
            title: 'Resolver consensus',
            status: $consensusMatches ? 'pass' : 'warning',
            severity: $consensusMatches ? 'S4' : 'S2',
            explanation: $consensusMatches
                ? 'Local resolver answers align with public resolvers for apex A records.'
                : 'Resolver answers differ across local/public resolvers, indicating possible propagation or cache variance.',
            suggestion: $consensusMatches
                ? 'Continue cross-resolver checks after major DNS changes.'
                : 'Wait for propagation or verify delegation/authoritative records if mismatch persists.',
            evidence: [
                'Local A: '.($resolverConsensus['local_a'] !== [] ? implode(', ', $resolverConsensus['local_a']) : 'none'),
                'Google A: '.($resolverConsensus['google_a'] !== [] ? implode(', ', $resolverConsensus['google_a']) : 'none'),
                'Cloudflare A: '.($resolverConsensus['cloudflare_a'] !== [] ? implode(', ', $resolverConsensus['cloudflare_a']) : 'none'),
                'Google error: '.($resolverConsensus['google_error'] ?? 'none'),
                'Cloudflare error: '.($resolverConsensus['cloudflare_error'] ?? 'none'),
            ],
        );

        $summary = [
            'total_checks' => count($checks),
            'passed' => count(array_filter($checks, static fn (array $item): bool => $item['status'] === 'pass')),
            'warnings' => count(array_filter($checks, static fn (array $item): bool => $item['status'] === 'warning')),
            'failed' => count(array_filter($checks, static fn (array $item): bool => $item['status'] === 'fail')),
        ];

        return [
            'target' => trim($target),
            'host' => $host,
            'scanned_at' => now()->toIso8601String(),
            'resolver_nameservers' => $this->resolverNameservers(),
            'summary' => $summary,
            'records' => [
                'a' => array_values(array_unique($a)),
                'aaaa' => array_values(array_unique($aaaa)),
                'ns' => array_values(array_unique($ns)),
                'parent_delegation_ns' => $parentNsNormalized,
                'mx' => array_values(array_unique($mx)),
                'txt' => array_values(array_unique($txt)),
                'soa' => array_values(array_unique($soa)),
                'cname' => array_values(array_unique($cname)),
                'caa' => array_values(array_unique($caa)),
                'dmarc' => array_values(array_unique($dmarc)),
                'dnskey' => array_values(array_unique($dnskey)),
                'www' => [
                    'a' => array_values(array_unique($wwwA)),
                    'aaaa' => array_values(array_unique($wwwAaaa)),
                    'cname' => array_values(array_unique($wwwCname)),
                ],
                'resolver_consensus' => $resolverConsensus,
                'nameserver_host_resolution' => $nsResolution,
            ],
            'checks' => $checks,
        ];
    }

    /**
     * @return array{key:string,title:string,status:string,severity:string,explanation:string,suggestion:string,evidence:array<int,string>}
     */
    private function result(
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

    /**
     * @return array<int,string>
     */
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

    /**
     * @param  array<int, string>  $hosts
     * @return array<int, string>
     */
    private function normalizeHostList(array $hosts): array
    {
        $normalized = [];

        foreach ($hosts as $host) {
            $value = strtolower(trim((string) $host));
            if ($value === '') {
                continue;
            }
            $normalized[] = rtrim($value, '.');
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array{source:string,nameservers:array<int,string>,error:string|null}
     */
    private function fetchParentDelegationNameservers(string $host): array
    {
        $url = 'https://rdap.org/domain/'.$host;

        try {
            $response = Http::timeout(8)->acceptJson()->get($url);
            if (! $response->ok()) {
                return [
                    'source' => $url,
                    'nameservers' => [],
                    'error' => 'RDAP HTTP '.$response->status(),
                ];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return [
                    'source' => $url,
                    'nameservers' => [],
                    'error' => 'Invalid RDAP response payload',
                ];
            }

            $nameservers = [];
            foreach ((array) ($payload['nameservers'] ?? []) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $ldhName = strtolower(trim((string) ($entry['ldhName'] ?? '')));
                if ($ldhName !== '') {
                    $nameservers[] = rtrim($ldhName, '.');
                }
            }

            return [
                'source' => $url,
                'nameservers' => array_values(array_unique($nameservers)),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'source' => $url,
                'nameservers' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, string>  $nameservers
     * @return array<string, array{a:array<int,string>,aaaa:array<int,string>}>
     */
    private function resolveNameserverHosts(array $nameservers): array
    {
        $result = [];

        foreach ($nameservers as $ns) {
            $fqdn = rtrim($ns, '.').'.';
            $records = @dns_get_record($fqdn, DNS_A + DNS_AAAA);
            $a = [];
            $aaaa = [];

            foreach ((array) $records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                if (($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                    $a[] = (string) $record['ip'];
                }

                if (($record['type'] ?? null) === 'AAAA' && isset($record['ipv6'])) {
                    $aaaa[] = (string) $record['ipv6'];
                }
            }

            $result[$ns] = [
                'a' => array_values(array_unique($a)),
                'aaaa' => array_values(array_unique($aaaa)),
            ];
        }

        return $result;
    }

    /**
     * @return array{
     *   local_a:array<int,string>,
     *   google_a:array<int,string>,
     *   cloudflare_a:array<int,string>,
     *   local_vs_google_match:bool,
     *   local_vs_cloudflare_match:bool,
     *   google_error:string|null,
     *   cloudflare_error:string|null
     * }
     */
    private function collectResolverConsensus(string $host): array
    {
        $localARecords = @dns_get_record(rtrim($host, '.').'.', DNS_A);
        $localA = [];
        foreach ((array) $localARecords as $record) {
            if (is_array($record) && ($record['type'] ?? null) === 'A' && isset($record['ip'])) {
                $localA[] = (string) $record['ip'];
            }
        }

        $google = $this->fetchDohA('https://dns.google/resolve', $host, []);
        $cloudflare = $this->fetchDohA('https://cloudflare-dns.com/dns-query', $host, ['accept' => 'application/dns-json']);

        $localSet = array_values(array_unique($localA));
        sort($localSet);

        $googleSet = array_values(array_unique($google['answers']));
        sort($googleSet);

        $cloudflareSet = array_values(array_unique($cloudflare['answers']));
        sort($cloudflareSet);

        return [
            'local_a' => $localSet,
            'google_a' => $googleSet,
            'cloudflare_a' => $cloudflareSet,
            'local_vs_google_match' => $localSet === $googleSet || $googleSet === [],
            'local_vs_cloudflare_match' => $localSet === $cloudflareSet || $cloudflareSet === [],
            'google_error' => $google['error'],
            'cloudflare_error' => $cloudflare['error'],
        ];
    }

    /**
     * @param  array<string,string>  $headers
     * @return array{answers:array<int,string>,error:string|null}
     */
    private function fetchDohA(string $baseUrl, string $host, array $headers): array
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders($headers)
                ->get($baseUrl, ['name' => $host, 'type' => 'A']);

            if (! $response->ok()) {
                return [
                    'answers' => [],
                    'error' => 'HTTP '.$response->status(),
                ];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return [
                    'answers' => [],
                    'error' => 'Invalid JSON payload',
                ];
            }

            $answers = [];
            foreach ((array) ($payload['Answer'] ?? []) as $answer) {
                if (! is_array($answer)) {
                    continue;
                }

                if ((int) ($answer['type'] ?? 0) === 1 && isset($answer['data'])) {
                    $answers[] = (string) $answer['data'];
                }
            }

            return [
                'answers' => array_values(array_unique($answers)),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'answers' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
