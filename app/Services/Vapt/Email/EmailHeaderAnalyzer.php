<?php

namespace App\Services\Vapt\Email;

class EmailHeaderAnalyzer
{
    private const MULTI_PART_SUFFIXES = ['co', 'com', 'net', 'org', 'gov', 'ac', 'edu', 'gen', 'firm', 'ind', 'ltd', 'plc', 'res'];

    public function __construct(
        private readonly DnsLookup $dns,
        private readonly EmailSecurityService $security,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $rawHeaders, ?int $size = null): array
    {
        $headers = $this->parseHeaders($rawHeaders);

        $fromAddress = $this->extractAddress($this->first($headers, 'from'));
        $fromDomain = $this->domainOf($fromAddress);
        $returnPath = $this->extractAddress($this->first($headers, 'return-path'));

        $auth = $this->authenticationResults($headers);
        $signatures = $this->dkimSignatures($headers);
        $sendingIp = $this->sendingIp($headers, $auth);
        $tls = $this->transportTls($headers, $sendingIp);

        $mailfromDomain = $this->domainOf($auth['spf']['mailfrom'] ?? null) ?? $this->domainOf($returnPath);
        $alignment = $this->alignment($auth, $fromDomain, $mailfromDomain);

        $ipInfo = null;
        $blacklist = null;
        if ($sendingIp !== null) {
            $ptr = $this->dns->ptr($sendingIp);
            $isV6 = str_contains($sendingIp, ':');
            $fcrdns = false;
            foreach ($ptr as $host) {
                $forward = $isV6 ? $this->dns->aaaa($host) : $this->dns->a($host);
                if (in_array($this->normalizeIp($sendingIp), array_map(fn (string $ip): string => $this->normalizeIp($ip), $forward), true)) {
                    $fcrdns = true;
                    break;
                }
            }
            $ipInfo = ['ip' => $sendingIp, 'version' => $isV6 ? 6 : 4, 'ptr' => $ptr, 'fcrdns' => $fcrdns];

            if (! $isV6 && $this->isPublicIp($sendingIp)) {
                $blacklist = $this->security->checkBlacklists([$sendingIp], $fromDomain);
            }
        }

        $listUnsubscribe = $this->first($headers, 'list-unsubscribe');
        $message = [
            'from' => $this->decode($this->first($headers, 'from')),
            'from_address' => $fromAddress,
            'from_domain' => $fromDomain,
            'return_path' => $returnPath,
            'subject' => $this->decode($this->first($headers, 'subject')),
            'message_id' => $this->first($headers, 'message-id'),
            'date' => $this->first($headers, 'date'),
            'x_mailer' => $this->first($headers, 'x-mailer') ?? $this->first($headers, 'user-agent'),
            'list_unsubscribe' => $listUnsubscribe !== null,
            'list_unsubscribe_one_click' => $this->first($headers, 'list-unsubscribe-post') !== null,
            'size' => $size,
            'received_hops' => count(array_filter($headers, static fn (array $h): bool => $h[0] === 'received')),
        ];

        [$checks, $breakdown] = $this->buildChecks($auth, $alignment, $signatures, $ipInfo, $blacklist, $tls, $message);

        $earned = array_sum(array_column($breakdown, 'earned'));
        $possible = array_sum(array_column($breakdown, 'possible'));
        $score = $possible > 0 ? (int) round($earned / $possible * 100) : 0;

        return [
            'score' => $score,
            'grade' => $this->security->grade($score),
            'breakdown' => $breakdown,
            'message' => $message,
            'authentication' => $auth,
            'alignment' => $alignment,
            'dkim_signatures' => $signatures,
            'sending_ip' => $ipInfo,
            'blacklist' => $blacklist,
            'tls' => $tls,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<int, array{0:string, 1:string}> lowercase name + unfolded value, in original order
     */
    public function parseHeaders(string $raw): array
    {
        $headers = [];

        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $headers !== []) {
                $headers[count($headers) - 1][1] .= ' '.trim($line);

                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[] = [strtolower(trim($name)), trim($value)];
        }

        return $headers;
    }

    /**
     * @param  array<int, array{0:string, 1:string}>  $headers
     */
    private function first(array $headers, string $name): ?string
    {
        foreach ($headers as [$headerName, $value]) {
            if ($headerName === $name) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{0:string, 1:string}>  $headers
     * @return array<int, string>
     */
    private function all(array $headers, string $name): array
    {
        return array_values(array_map(
            static fn (array $h): string => $h[1],
            array_filter($headers, static fn (array $h): bool => $h[0] === $name),
        ));
    }

    /**
     * @param  array<int, array{0:string, 1:string}>  $headers
     * @return array{authserv_id:string, raw:string, spf:?array<string,mixed>, dkim:array<int,array<string,mixed>>, dmarc:?array<string,mixed>}|null
     */
    public function authenticationResults(array $headers): ?array
    {
        $trusted = strtolower(trim((string) config('email-security.test_mailbox.trusted_authserv_id', '')));

        foreach ($this->all($headers, 'authentication-results') as $value) {
            $parts = $this->splitOutsideComments($value);
            $authservId = strtolower(trim(strtok(trim($parts[0] ?? ''), " \t") ?: ''));

            // Microsoft 365 omits the authserv-id and starts directly with "spf=...".
            $hasAuthservId = ! str_contains($authservId, '=');
            if (! $hasAuthservId) {
                $authservId = '';
            }

            if ($trusted !== '' && $authservId !== $trusted) {
                continue;
            }

            $result = ['authserv_id' => $authservId, 'raw' => $value, 'spf' => null, 'dkim' => [], 'dmarc' => null];

            foreach (array_slice($parts, $hasAuthservId ? 1 : 0) as $part) {
                $parsed = $this->parseMethodResult($part);
                if ($parsed === null) {
                    continue;
                }

                match ($parsed['method']) {
                    'spf' => $result['spf'] ??= [
                        'result' => $parsed['result'],
                        'mailfrom' => $parsed['props']['smtp.mailfrom'] ?? null,
                        'comment' => $parsed['comment'],
                    ],
                    'dkim' => $result['dkim'][] = [
                        'result' => $parsed['result'],
                        'domain' => $parsed['props']['header.d'] ?? $this->domainOf($parsed['props']['header.i'] ?? null),
                        'selector' => $parsed['props']['header.s'] ?? null,
                        'comment' => $parsed['comment'],
                    ],
                    'dmarc' => $result['dmarc'] ??= [
                        'result' => $parsed['result'],
                        'header_from' => $parsed['props']['header.from'] ?? null,
                        'policy' => $this->dmarcPolicyFromComment($parsed['comment'], $part),
                        'comment' => $parsed['comment'],
                    ],
                    default => null,
                };
            }

            return $result;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function splitOutsideComments(string $value): array
    {
        $parts = [];
        $depth = 0;
        $current = '';

        foreach (str_split($value) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }

            if ($char === ';' && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }

    /**
     * @return array{method:string, result:string, props:array<string,string>, comment:?string}|null
     */
    private function parseMethodResult(string $part): ?array
    {
        $comment = preg_match('/\(([^)]*)\)/', $part, $m) === 1 ? trim($m[1]) : null;
        $clean = preg_replace('/\([^)]*\)/', ' ', $part) ?? $part;

        if (preg_match('/^\s*([a-z0-9-]+)\s*=\s*([a-z]+)/i', $clean, $match) !== 1) {
            return null;
        }

        $props = [];
        if (preg_match_all('/([a-z]+\.[a-z0-9_-]+)\s*=\s*("[^"]*"|[^\s;]+)/i', $clean, $pairs, PREG_SET_ORDER) > 0) {
            foreach ($pairs as $pair) {
                $props[strtolower($pair[1])] = trim($pair[2], '"');
            }
        }

        return [
            'method' => strtolower($match[1]),
            'result' => strtolower($match[2]),
            'props' => $props,
            'comment' => $comment,
        ];
    }

    private function dmarcPolicyFromComment(?string $comment, string $part): ?string
    {
        foreach ([$comment ?? '', $part] as $text) {
            if (preg_match('/\bp=([a-z]+)/i', $text, $m) === 1) {
                return strtolower($m[1]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{0:string, 1:string}>  $headers
     * @return array<int, array{domain:?string, selector:?string, algorithm:?string, key:?array<string,mixed>}>
     */
    private function dkimSignatures(array $headers): array
    {
        $signatures = [];

        foreach (array_slice($this->all($headers, 'dkim-signature'), 0, 3) as $value) {
            $tags = [];
            foreach (explode(';', $value) as $part) {
                if (str_contains($part, '=')) {
                    [$k, $v] = explode('=', $part, 2);
                    $tags[strtolower(trim($k))] = trim(preg_replace('/\s+/', '', $v) ?? $v);
                }
            }

            $domain = isset($tags['d']) ? strtolower($tags['d']) : null;
            $selector = isset($tags['s']) ? strtolower($tags['s']) : null;
            $key = null;

            if ($domain !== null && $selector !== null && preg_match('/^[a-z0-9._-]+$/', $selector.$domain) === 1) {
                foreach ($this->dns->txt($selector.'._domainkey.'.$domain) as $record) {
                    if (preg_match('/(^|;)\s*p=/i', $record) === 1) {
                        $key = $this->security->parseDkimKey($selector, $record);
                        break;
                    }
                }
            }

            $signatures[] = [
                'domain' => $domain,
                'selector' => $selector,
                'algorithm' => isset($tags['a']) ? strtolower($tags['a']) : null,
                'key' => $key,
            ];
        }

        return $signatures;
    }

    /**
     * @param  array<int, array{0:string, 1:string}>  $headers
     * @param  array<string, mixed>|null  $auth
     */
    private function sendingIp(array $headers, ?array $auth): ?string
    {
        $receivedSpf = $this->first($headers, 'received-spf');
        if ($receivedSpf !== null && preg_match('/client-ip=([0-9a-f.:]+)/i', $receivedSpf, $m) === 1 && filter_var($m[1], FILTER_VALIDATE_IP)) {
            return $m[1];
        }

        $comment = (string) ($auth['spf']['comment'] ?? '');
        if (preg_match('/(?:designates|sender ip is|client-ip=)\s*([0-9a-f.:]+)/i', $comment, $m) === 1 && filter_var(rtrim($m[1], '.'), FILTER_VALIDATE_IP)) {
            return rtrim($m[1], '.');
        }

        foreach ($this->all($headers, 'received') as $received) {
            if (preg_match_all('/\[([0-9a-f.:]+)\]/i', $received, $matches) > 0) {
                foreach ($matches[1] as $candidate) {
                    $candidate = preg_replace('/^ipv6:/i', '', $candidate) ?? $candidate;
                    if (filter_var($candidate, FILTER_VALIDATE_IP) && $this->isPublicIp($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{0:string, 1:string}>  $headers
     * @return array{used:?bool, detail:?string}
     */
    private function transportTls(array $headers, ?string $sendingIp): array
    {
        $received = $this->all($headers, 'received');
        $hop = null;

        if ($sendingIp !== null) {
            foreach ($received as $value) {
                if (str_contains(strtolower($value), strtolower($sendingIp))) {
                    $hop = $value;
                    break;
                }
            }
        }
        $hop ??= $received[0] ?? null;

        if ($hop === null) {
            return ['used' => null, 'detail' => null];
        }

        if (preg_match('/(version=TLS[0-9_.]+|TLSv?[0-9.]+|cipher=[A-Z0-9_-]+)/i', $hop, $m) === 1) {
            return ['used' => true, 'detail' => $m[1]];
        }

        if (preg_match('/\bwith\s+(E?SMTPS[A-Z]*|UTF8SMTPS[A-Z]*|ESMTPSA)\b/i', $hop, $m) === 1) {
            return ['used' => true, 'detail' => 'with '.$m[1]];
        }

        if (preg_match('/\bwith\s+(E?SMTP|UTF8SMTP)\b/i', $hop, $m) === 1) {
            return ['used' => false, 'detail' => 'with '.$m[1]];
        }

        return ['used' => null, 'detail' => null];
    }

    /**
     * @param  array<string, mixed>|null  $auth
     * @return array{from_domain:?string, mailfrom_domain:?string, dkim_domains:array<int,string>, dkim_aligned:bool, spf_aligned:bool}
     */
    private function alignment(?array $auth, ?string $fromDomain, ?string $mailfromDomain): array
    {
        $dkimDomains = [];
        $dkimAligned = false;

        foreach ($auth['dkim'] ?? [] as $dkim) {
            if ($dkim['domain'] === null) {
                continue;
            }
            $dkimDomains[] = strtolower($dkim['domain']);
            if ($dkim['result'] === 'pass' && $fromDomain !== null && $this->orgDomain($dkim['domain']) === $this->orgDomain($fromDomain)) {
                $dkimAligned = true;
            }
        }

        $spfAligned = ($auth['spf']['result'] ?? null) === 'pass'
            && $fromDomain !== null
            && $mailfromDomain !== null
            && $this->orgDomain($mailfromDomain) === $this->orgDomain($fromDomain);

        return [
            'from_domain' => $fromDomain,
            'mailfrom_domain' => $mailfromDomain,
            'dkim_domains' => array_values(array_unique($dkimDomains)),
            'dkim_aligned' => $dkimAligned,
            'spf_aligned' => $spfAligned,
        ];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{key:string, label:string, earned:int, possible:int}>}
     */
    private function buildChecks(?array $auth, array $alignment, array $signatures, ?array $ipInfo, ?array $blacklist, array $tls, array $message): array
    {
        $checks = [];
        $breakdown = [];

        if ($auth === null) {
            $checks[] = $this->result('auth-results', 'Receiver authentication results', 'warning', 'S2',
                'The receiving server did not add an Authentication-Results header, so SPF, DKIM and DMARC verdicts are unavailable.',
                'Use a test mailbox hosted on Google Workspace, Microsoft 365 or another provider that records authentication results.',
                []);
        } else {
            $spf = $auth['spf']['result'] ?? 'none';
            [$status, $severity, $points] = match ($spf) {
                'pass' => ['pass', 'S4', 15],
                'softfail', 'neutral' => ['warning', 'S2', 7],
                'none' => ['warning', 'S2', 0],
                default => ['fail', 'S1', 0],
            };
            $checks[] = $this->result('spf', 'SPF result', $status, $severity,
                'The receiving server evaluated SPF as "'.$spf.'".',
                $spf === 'pass'
                    ? 'No action needed.'
                    : 'Add the sending server or provider shown below to the SPF record of '.($alignment['mailfrom_domain'] ?? 'the envelope sender domain').'.',
                array_filter([
                    'Envelope sender (MAIL FROM): '.($auth['spf']['mailfrom'] ?? $message['return_path'] ?? 'unknown'),
                    $auth['spf']['comment'] ?? null,
                ]));
            $breakdown[] = ['key' => 'spf', 'label' => 'SPF', 'earned' => $points, 'possible' => 15];

            $dkimResults = $auth['dkim'];
            $dkimPass = array_values(array_filter($dkimResults, static fn (array $d): bool => $d['result'] === 'pass'));
            if ($dkimPass !== []) {
                $checks[] = $this->result('dkim', 'DKIM result', 'pass', 'S4',
                    'The message carried a valid DKIM signature from '.implode(', ', array_unique(array_filter(array_column($dkimPass, 'domain')))).'.',
                    'No action needed.',
                    array_map(static fn (array $d): string => ($d['domain'] ?? '?').' (selector '.($d['selector'] ?? '?').'): '.$d['result'], $dkimResults));
                $breakdown[] = ['key' => 'dkim', 'label' => 'DKIM', 'earned' => 20, 'possible' => 20];
            } else {
                $unsigned = $dkimResults === [] || array_column($dkimResults, 'result') === ['none'];
                $checks[] = $this->result('dkim', 'DKIM result', 'fail', 'S1',
                    $unsigned ? 'The message was not DKIM-signed.' : 'The DKIM signature did not verify (the message was altered or the key does not match).',
                    $unsigned
                        ? 'Enable DKIM signing in your mail server or email provider and publish the public key in DNS.'
                        : 'Check that the published DKIM key matches the private key your server signs with.',
                    array_map(static fn (array $d): string => ($d['domain'] ?? '?').' (selector '.($d['selector'] ?? '?').'): '.$d['result'], $dkimResults));
                $breakdown[] = ['key' => 'dkim', 'label' => 'DKIM', 'earned' => 0, 'possible' => 20];
            }

            $dmarc = $auth['dmarc']['result'] ?? 'none';
            [$status, $severity, $points, $text] = match ($dmarc) {
                'pass' => ['pass', 'S4', 15, 'DMARC passed'.(($auth['dmarc']['policy'] ?? null) ? ' (policy '.$auth['dmarc']['policy'].')' : '').'.'],
                'bestguesspass' => ['warning', 'S3', 5, 'The domain has no DMARC record; the receiver guessed it would pass.'],
                'none' => ['warning', 'S2', 0, 'No DMARC policy was found for the From domain.'],
                default => ['fail', 'S1', 0, 'DMARC failed: neither SPF nor DKIM passed for a domain matching the From address.'],
            };
            $checks[] = $this->result('dmarc', 'DMARC result', $status, $severity, $text,
                $dmarc === 'pass' ? 'No action needed.' : 'Publish a DMARC record and make sure DKIM signs with the From domain.',
                array_filter(['Header From domain: '.($auth['dmarc']['header_from'] ?? $alignment['from_domain'] ?? 'unknown'), $auth['dmarc']['comment'] ?? null]));
            $breakdown[] = ['key' => 'dmarc', 'label' => 'DMARC', 'earned' => $points, 'possible' => 15];

            $alignEvidence = [
                'From domain: '.($alignment['from_domain'] ?? 'unknown'),
                'DKIM signing domains: '.($alignment['dkim_domains'] !== [] ? implode(', ', $alignment['dkim_domains']) : 'none'),
                'Envelope sender domain: '.($alignment['mailfrom_domain'] ?? 'unknown'),
            ];
            if ($alignment['dkim_aligned']) {
                $checks[] = $this->result('alignment', 'Domain alignment', 'pass', 'S4',
                    'DKIM is signed by the same domain as the From address, which survives forwarding.',
                    'No action needed.', $alignEvidence);
                $breakdown[] = ['key' => 'alignment', 'label' => 'Alignment', 'earned' => 10, 'possible' => 10];
            } elseif ($alignment['spf_aligned']) {
                $checks[] = $this->result('alignment', 'Domain alignment', 'warning', 'S3',
                    'Only SPF is aligned with the From domain. SPF breaks when mail is forwarded, so DMARC can fail at the final recipient.',
                    'Sign with DKIM using the From domain (d='.($alignment['from_domain'] ?? 'yourdomain').').', $alignEvidence);
                $breakdown[] = ['key' => 'alignment', 'label' => 'Alignment', 'earned' => 6, 'possible' => 10];
            } else {
                $checks[] = $this->result('alignment', 'Domain alignment', 'fail', 'S1',
                    'Neither DKIM nor SPF passed for a domain matching the From address. DMARC fails and mail may be rejected or sent to spam.',
                    'Configure your email provider to sign DKIM with your own domain (custom domain authentication) instead of the provider\'s domain.',
                    $alignEvidence);
                $breakdown[] = ['key' => 'alignment', 'label' => 'Alignment', 'earned' => 0, 'possible' => 10];
            }
        }

        $signed = array_values(array_filter($signatures, static fn (array $s): bool => $s['domain'] !== null));
        if ($signed !== []) {
            $sig = $signed[0];
            $key = $sig['key'];
            $evidence = ['Signature: d='.$sig['domain'].' s='.$sig['selector'].' a='.($sig['algorithm'] ?? '?')];
            if ($key === null) {
                $checks[] = $this->result('dkim-key', 'DKIM key strength', 'warning', 'S2',
                    'The DKIM public key for selector "'.$sig['selector'].'" could not be found in DNS.',
                    'Publish the key at '.$sig['selector'].'._domainkey.'.$sig['domain'].'.', $evidence);
                $breakdown[] = ['key' => 'dkim_key', 'label' => 'DKIM key', 'earned' => 0, 'possible' => 5];
            } else {
                $bits = $key['key_type'] === 'ed25519' ? 4096 : (int) ($key['key_bits'] ?? 0);
                $evidence[] = 'Key: '.strtoupper($key['key_type']).($key['key_bits'] ? ' '.$key['key_bits'].'-bit' : '');
                $sha1 = ($sig['algorithm'] ?? '') === 'rsa-sha1';
                if ($bits > 0 && $bits < 1024) {
                    $checks[] = $this->result('dkim-key', 'DKIM key strength', 'fail', 'S1',
                        'The DKIM key is weaker than 1024 bits and is rejected by major mailbox providers.',
                        'Rotate to a 2048-bit RSA key.', $evidence);
                    $breakdown[] = ['key' => 'dkim_key', 'label' => 'DKIM key', 'earned' => 0, 'possible' => 5];
                } elseif (($bits > 0 && $bits < 2048) || $sha1) {
                    $checks[] = $this->result('dkim-key', 'DKIM key strength', 'warning', 'S3',
                        $sha1 ? 'The message is signed with rsa-sha1, which is deprecated.' : 'The DKIM key is 1024 bits.',
                        'Use a 2048-bit RSA key with rsa-sha256.', $evidence);
                    $breakdown[] = ['key' => 'dkim_key', 'label' => 'DKIM key', 'earned' => 3, 'possible' => 5];
                } else {
                    $checks[] = $this->result('dkim-key', 'DKIM key strength', 'pass', 'S4',
                        'The signing key is strong.', 'Rotate DKIM keys periodically.', $evidence);
                    $breakdown[] = ['key' => 'dkim_key', 'label' => 'DKIM key', 'earned' => 5, 'possible' => 5];
                }
            }
        }

        if ($ipInfo !== null) {
            $evidence = ['Sending IP: '.$ipInfo['ip'], 'PTR: '.($ipInfo['ptr'] !== [] ? implode(', ', $ipInfo['ptr']) : 'none')];
            if ($ipInfo['ptr'] === []) {
                $checks[] = $this->result('sending-ip-rdns', 'Sending server reverse DNS', 'fail', 'S2',
                    'The sending IP has no reverse DNS (PTR) record. Many receivers reject or spam-folder such mail.',
                    'Ask your server or IP provider to set a PTR record matching the server hostname.', $evidence);
                $breakdown[] = ['key' => 'rdns', 'label' => 'Reverse DNS', 'earned' => 0, 'possible' => 10];
            } elseif (! $ipInfo['fcrdns']) {
                $checks[] = $this->result('sending-ip-rdns', 'Sending server reverse DNS', 'warning', 'S3',
                    'The PTR hostname does not resolve back to the sending IP.',
                    'Add an A/AAAA record for the PTR hostname pointing to the sending IP.', $evidence);
                $breakdown[] = ['key' => 'rdns', 'label' => 'Reverse DNS', 'earned' => 5, 'possible' => 10];
            } else {
                $checks[] = $this->result('sending-ip-rdns', 'Sending server reverse DNS', 'pass', 'S4',
                    'The sending IP has matching forward and reverse DNS.', 'No action needed.', $evidence);
                $breakdown[] = ['key' => 'rdns', 'label' => 'Reverse DNS', 'earned' => 10, 'possible' => 10];
            }
        }

        if ($blacklist !== null && $blacklist['enabled'] && $blacklist['checked'] > 0) {
            $listed = array_values(array_filter($blacklist['results'], static fn (array $r): bool => $r['status'] === 'listed'));
            $evidence = array_merge(
                ['Lookups: '.$blacklist['checked'].' · Listed: '.$blacklist['listed'].' · Unverified: '.$blacklist['errors']],
                array_map(static fn (array $r): string => $r['target'].' on '.$r['list'].' ('.$r['response'].')', $listed),
            );
            if ($listed !== []) {
                $checks[] = $this->result('sending-ip-blacklist', 'Sending IP / domain blacklists', 'fail', 'S1',
                    'The sending IP or From domain is on '.count($listed).' blacklist'.(count($listed) === 1 ? '' : 's').'.',
                    'Fix the cause (compromised account, open relay, bulk mail) and request delisting.', $evidence);
                $breakdown[] = ['key' => 'blacklist', 'label' => 'Blacklists', 'earned' => 0, 'possible' => 15];
            } elseif ($blacklist['errors'] === $blacklist['checked']) {
                $checks[] = $this->result('sending-ip-blacklist', 'Sending IP / domain blacklists', 'warning', 'S3',
                    'Blacklist status could not be verified from this server.',
                    'Configure a Spamhaus DQS key or run from a server with its own DNS resolver.', $evidence);
                $breakdown[] = ['key' => 'blacklist', 'label' => 'Blacklists', 'earned' => 10, 'possible' => 15];
            } else {
                $checks[] = $this->result('sending-ip-blacklist', 'Sending IP / domain blacklists', 'pass', 'S4',
                    'The sending IP and From domain are not on the checked blacklists.', 'No action needed.', $evidence);
                $breakdown[] = ['key' => 'blacklist', 'label' => 'Blacklists', 'earned' => 15, 'possible' => 15];
            }
        }

        if ($tls['used'] === true) {
            $checks[] = $this->result('transport-tls', 'Encrypted delivery (TLS)', 'pass', 'S4',
                'The message was delivered over an encrypted connection.', 'No action needed.', array_filter([$tls['detail']]));
            $breakdown[] = ['key' => 'tls', 'label' => 'TLS', 'earned' => 10, 'possible' => 10];
        } elseif ($tls['used'] === false) {
            $checks[] = $this->result('transport-tls', 'Encrypted delivery (TLS)', 'warning', 'S2',
                'The message was delivered without TLS encryption.',
                'Enable STARTTLS for outgoing mail on your sending server.', array_filter([$tls['detail']]));
            $breakdown[] = ['key' => 'tls', 'label' => 'TLS', 'earned' => 0, 'possible' => 10];
        }

        $checks[] = $message['list_unsubscribe']
            ? $this->result('list-unsubscribe', 'List-Unsubscribe header', 'pass', 'S4',
                'A List-Unsubscribe header is present'.($message['list_unsubscribe_one_click'] ? ' with one-click unsubscribe.' : '.'),
                $message['list_unsubscribe_one_click'] ? 'No action needed.' : 'Add List-Unsubscribe-Post: List-Unsubscribe=One-Click for bulk mail.',
                [])
            : $this->result('list-unsubscribe', 'List-Unsubscribe header', 'warning', 'S4',
                'No List-Unsubscribe header. This is fine for personal mail but required by Gmail and Yahoo for bulk senders.',
                'For newsletters and marketing mail, add List-Unsubscribe and List-Unsubscribe-Post headers.',
                []);

        if ($message['message_id'] === null || $message['date'] === null) {
            $checks[] = $this->result('message-headers', 'Required headers', 'warning', 'S3',
                'The message is missing a '.($message['message_id'] === null ? 'Message-ID' : 'Date').' header, which spam filters penalise.',
                'Make sure your mail software sets Message-ID and Date on every message.', []);
        }

        return [$checks, $breakdown];
    }

    private function extractAddress(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/<([^>]+@[^>]+)>/', $value, $m) === 1) {
            return strtolower(trim($m[1]));
        }

        if (preg_match('/[A-Z0-9._%+=-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value, $m) === 1) {
            return strtolower($m[0]);
        }

        return null;
    }

    private function domainOf(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = strtolower(trim($value, " <>\t"));
        $domain = str_contains($value, '@') ? substr($value, strrpos($value, '@') + 1) : $value;

        return $domain !== '' ? rtrim($domain, '.') : null;
    }

    public function orgDomain(string $domain): string
    {
        $labels = explode('.', strtolower(rtrim($domain, '.')));
        $count = count($labels);

        if ($count >= 3 && strlen($labels[$count - 1]) === 2 && in_array($labels[$count - 2], self::MULTI_PART_SUFFIXES, true)) {
            return implode('.', array_slice($labels, -3));
        }

        return implode('.', array_slice($labels, -2));
    }

    private function decode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = function_exists('iconv_mime_decode') ? @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : false;

        return mb_substr($decoded !== false ? $decoded : $value, 0, 500);
    }

    private function normalizeIp(string $ip): string
    {
        $packed = @inet_pton($ip);

        return $packed !== false ? (string) inet_ntop($packed) : strtolower($ip);
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param  array<int, string|null>  $evidence
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
            'evidence' => array_values(array_filter($evidence, static fn ($e): bool => $e !== null && $e !== '')),
        ];
    }
}
