<?php

namespace App\EmailMonitoring\Infrastructure\Connectors;

use App\EmailMonitoring\Contracts\MailboxConnectorInterface;
use App\EmailMonitoring\DTO\MailboxSyncCursor;
use App\EmailMonitoring\DTO\RawEmailEnvelope;
use App\EmailMonitoring\Enums\MailboxDriver;
use App\Models\EmailMailbox;
use Generator;
use RuntimeException;

/**
 * Native IMAP mailbox connector.
 */
class ImapMailboxConnector implements MailboxConnectorInterface
{
    public function driver(): string
    {
        return 'imap';
    }

    public function fetchSince(EmailMailbox $mailbox, ?MailboxSyncCursor $cursor = null): Generator
    {
        if ($mailbox->driver !== MailboxDriver::Imap) {
            yield from [];

            return;
        }

        $config = $this->validatedConfig($mailbox);
        $cursorState = $cursor?->state ?? [];
        $lastUid = isset($cursorState['last_uid']) && is_numeric($cursorState['last_uid'])
            ? (int) $cursorState['last_uid']
            : 0;

        $stream = $this->openMailbox($config);

        try {
            $uids = $this->searchNewMessageUids($stream, $lastUid);
            $maxUid = $lastUid;

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0) {
                    continue;
                }

                $overviewItems = @imap_fetch_overview($stream, (string) $uid, FT_UID);
                $overview = is_array($overviewItems) && isset($overviewItems[0]) && is_object($overviewItems[0])
                    ? $overviewItems[0]
                    : null;

                $messageId = $this->normalizeMessageId((string) ($overview->message_id ?? ''));
                if ($messageId === null) {
                    $messageId = sprintf('imap-%s-%d', (string) $mailbox->id, $uid);
                }

                $rawHeaders = @imap_fetchheader($stream, (string) $uid, FT_UID) ?: null;
                $bodyText = @imap_body($stream, (string) $uid, FT_UID | FT_PEEK) ?: null;

                yield new RawEmailEnvelope(
                    externalMessageId: $messageId,
                    inReplyTo: $this->normalizeMessageId((string) ($overview->in_reply_to ?? '')),
                    subject: isset($overview->subject) ? (string) $overview->subject : null,
                    fromEmail: isset($overview->from)
                        ? $this->extractFirstEmailAddress((string) $overview->from)
                        : null,
                    toRecipients: isset($overview->to)
                        ? $this->extractEmailAddresses((string) $overview->to)
                        : [],
                    ccRecipients: isset($overview->cc)
                        ? $this->extractEmailAddresses((string) $overview->cc)
                        : [],
                    receivedAtIso: isset($overview->date)
                        ? date(DATE_ATOM, strtotime((string) $overview->date) ?: time())
                        : null,
                    bodyText: $bodyText,
                    bodyHtml: null,
                    headers: $rawHeaders !== null ? ['raw' => $rawHeaders] : [],
                    attachments: [],
                    raw: [
                        'uid' => $uid,
                        'imap_overview' => $overview ? (array) $overview : [],
                    ],
                );

                if ($uid > $maxUid) {
                    $maxUid = $uid;
                }
            }

            if ($maxUid > $lastUid) {
                $syncState = $mailbox->sync_state ?? [];
                if (! is_array($syncState)) {
                    $syncState = [];
                }
                $syncState['last_uid'] = $maxUid;
                $mailbox->updateQuietly(['sync_state' => $syncState]);
            }
        } finally {
            @imap_close($stream);
        }
    }

    public function healthCheck(EmailMailbox $mailbox): bool
    {
        if ($mailbox->driver !== MailboxDriver::Imap) {
            return false;
        }

        $config = $this->validatedConfig($mailbox);
        $stream = null;

        try {
            $stream = $this->openMailbox($config);

            return true;
        } finally {
            if ($stream) {
                @imap_close($stream);
            }
            $this->drainImapDiagnostics();
        }
    }

    /**
     * @return array{host: string, port: int, encryption: string, username: string, password: string, folder: string}
     */
    private function validatedConfig(EmailMailbox $mailbox): array
    {
        $config = is_array($mailbox->connection_config) ? $mailbox->connection_config : [];

        $host = trim((string) ($config['host'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $folder = trim((string) ($config['folder'] ?? 'INBOX'));
        $port = (int) ($config['port'] ?? 993);
        $encryption = strtolower((string) ($config['encryption'] ?? 'ssl'));

        if ($host === '' || $username === '' || $password === '') {
            throw new RuntimeException('IMAP mailbox is missing required connection settings (host, username, password).');
        }

        if (! in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            $encryption = 'ssl';
        }

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'folder' => $folder !== '' ? $folder : 'INBOX',
        ];
    }

    /**
     * @param  array{host: string, port: int, encryption: string, username: string, password: string, folder: string}  $config
     * @return \IMAP\Connection|resource
     */
    private function openMailbox(array $config)
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException('IMAP extension is not installed on this server. Install php-imap (ext-imap) to enable IMAP polling.');
        }

        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }

        $mailboxPath = sprintf('{%s:%d%s}%s', $config['host'], $config['port'], $flags, $config['folder']);

        $this->drainImapDiagnostics();
        $stream = @imap_open($mailboxPath, $config['username'], $config['password'], 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);

        if (! $stream) {
            $lastError = imap_last_error() ?: 'unknown IMAP error';
            $this->drainImapDiagnostics();
            $message = $this->mapWorkspaceFriendlyError($lastError);
            throw new RuntimeException($message);
        }

        $this->drainImapDiagnostics();

        return $stream;
    }

    /**
     * @param  \IMAP\Connection|resource  $stream
     * @return array<int, int>
     */
    private function searchNewMessageUids($stream, int $lastUid): array
    {
        if ($lastUid > 0) {
            $criteria = 'UID '.($lastUid + 1).':*';
            $uids = @imap_search($stream, $criteria, SE_UID);
            if (is_array($uids)) {
                return array_values(array_map('intval', $uids));
            }
        }

        $fallback = @imap_search($stream, 'ALL', SE_UID);

        return is_array($fallback)
            ? array_values(array_map('intval', $fallback))
            : [];
    }

    /**
     * Clear queued IMAP warnings so PHP does not emit a second error during request shutdown
     * (which breaks JSON API responses when APP_DEBUG is enabled).
     */
    private function drainImapDiagnostics(): void
    {
        if (! function_exists('imap_errors')) {
            return;
        }

        imap_errors();
        imap_alerts();
    }

    private function mapWorkspaceFriendlyError(string $error): string
    {
        $lower = strtolower($error);

        if (str_contains($lower, 'auth') || str_contains($lower, 'login') || str_contains($lower, 'invalid credentials')) {
            return 'IMAP authentication failed. For Google Workspace, ensure IMAP is enabled for the user and use an app password, or switch this mailbox to Gmail API OAuth.';
        }

        if (str_contains($lower, 'certificate') || str_contains($lower, 'tls') || str_contains($lower, 'ssl')) {
            return 'IMAP TLS/SSL handshake failed. Verify host, port, and encryption settings (ssl/tls/none).';
        }

        if (str_contains($lower, 'connection') || str_contains($lower, 'timed out') || str_contains($lower, 'refused')) {
            return 'IMAP server connection failed. Verify mailbox host/port and server network egress/firewall rules.';
        }

        return 'IMAP mailbox connection failed: '.$error;
    }

    /**
     * @return array<int, string>
     */
    private function extractEmailAddresses(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $raw, $matches);
        $emails = $matches[0] ?? [];

        return array_values(array_unique(array_map(static fn (string $email): string => strtolower(trim($email)), $emails)));
    }

    private function extractFirstEmailAddress(string $raw): ?string
    {
        $emails = $this->extractEmailAddresses($raw);

        return $emails[0] ?? null;
    }

    private function normalizeMessageId(string $raw): ?string
    {
        $clean = trim($raw);
        if ($clean === '') {
            return null;
        }

        return trim($clean, " <>\t\n\r\0\x0B");
    }
}
