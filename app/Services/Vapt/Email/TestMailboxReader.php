<?php

namespace App\Services\Vapt\Email;

use IMAP\Connection;
use RuntimeException;

class TestMailboxReader
{
    private const MAX_MESSAGES_SCANNED = 200;

    public function isConfigured(): bool
    {
        $config = (array) config('email-security.test_mailbox', []);

        return trim((string) ($config['address'] ?? '')) !== ''
            && trim((string) ($config['host'] ?? '')) !== ''
            && trim((string) ($config['username'] ?? '')) !== ''
            && (string) ($config['password'] ?? '') !== '';
    }

    /**
     * Finds messages addressed to (or mentioning) any of the given tokens.
     *
     * @param  array<int, string>  $tokens
     * @return array<string, array{raw_headers:string, size:int|null}> keyed by token
     */
    public function findMessages(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        if (! function_exists('imap_open')) {
            throw new RuntimeException('The IMAP extension is not installed on this server.');
        }

        $config = (array) config('email-security.test_mailbox', []);
        $stream = $this->open($config);
        $found = [];

        try {
            $uids = @imap_search($stream, 'SINCE "'.date('d-M-Y', strtotime('-1 day')).'"', SE_UID);
            $uids = is_array($uids) ? array_slice(array_map('intval', $uids), -self::MAX_MESSAGES_SCANNED) : [];
            $matchedUids = [];

            foreach (array_reverse($uids) as $uid) {
                $headers = @imap_fetchheader($stream, (string) $uid, FT_UID);
                if (! is_string($headers) || $headers === '') {
                    continue;
                }

                $token = $this->matchToken($headers, $tokens);
                if ($token === null || isset($found[$token])) {
                    continue;
                }

                $overview = @imap_fetch_overview($stream, (string) $uid, FT_UID);
                $found[$token] = [
                    'raw_headers' => $headers,
                    'size' => isset($overview[0]->size) ? (int) $overview[0]->size : null,
                ];
                $matchedUids[] = $uid;
            }

            if ($matchedUids !== [] && (bool) ($config['delete_after_read'] ?? true)) {
                foreach ($matchedUids as $uid) {
                    @imap_delete($stream, (string) $uid, FT_UID);
                }
                @imap_expunge($stream);
            }
        } finally {
            @imap_close($stream);
            imap_errors();
            imap_alerts();
        }

        return $found;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function matchToken(string $rawHeaders, array $tokens): ?string
    {
        $haystack = strtolower($rawHeaders);

        foreach ($tokens as $token) {
            if (str_contains($haystack, 'vp-'.strtolower($token))) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return Connection|resource
     */
    private function open(array $config)
    {
        $encryption = strtolower((string) ($config['encryption'] ?? 'ssl'));
        $flags = '/imap'.match ($encryption) {
            'tls' => '/tls',
            'none' => '/notls',
            default => '/ssl',
        };

        $path = sprintf('{%s:%d%s}%s', $config['host'], (int) ($config['port'] ?? 993), $flags, $config['folder'] ?? 'INBOX');

        imap_errors();
        $stream = @imap_open($path, (string) $config['username'], (string) $config['password'], 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);

        if (! $stream) {
            $error = imap_last_error() ?: 'unknown IMAP error';
            imap_errors();
            imap_alerts();

            throw new RuntimeException('Test mailbox connection failed: '.$error);
        }

        return $stream;
    }
}
