<?php

namespace App\EmailMonitoring\DTO;

/**
 * Immutable snapshot from a transport (IMAP / Gmail API / webhook).
 *
 * @param  array<string, mixed>  $headers
 * @param  array<int, array{filename?: string, mime?: string, size?: int, content_id?: string|null, inline?: bool, raw_path?: string|null}>  $attachments
 */
final readonly class RawEmailEnvelope
{
    public function __construct(
        public string $externalMessageId,
        public ?string $inReplyTo,
        public ?string $subject,
        public ?string $fromEmail,
        public array $toRecipients,
        public array $ccRecipients,
        public ?string $receivedAtIso,
        public ?string $bodyText,
        public ?string $bodyHtml,
        public array $headers,
        public array $attachments,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            externalMessageId: (string) ($payload['external_message_id'] ?? $payload['id'] ?? ''),
            inReplyTo: isset($payload['in_reply_to']) ? (string) $payload['in_reply_to'] : null,
            subject: isset($payload['subject']) ? (string) $payload['subject'] : null,
            fromEmail: isset($payload['from_email']) ? (string) $payload['from_email'] : null,
            toRecipients: (array) ($payload['to_recipients'] ?? []),
            ccRecipients: (array) ($payload['cc_recipients'] ?? []),
            receivedAtIso: isset($payload['received_at']) ? (string) $payload['received_at'] : null,
            bodyText: isset($payload['body_text']) ? (string) $payload['body_text'] : null,
            bodyHtml: isset($payload['body_html']) ? (string) $payload['body_html'] : null,
            headers: (array) ($payload['headers'] ?? []),
            attachments: (array) ($payload['attachments'] ?? []),
            raw: (array) ($payload['raw'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        return [
            'external_message_id' => $this->externalMessageId,
            'in_reply_to' => $this->inReplyTo,
            'subject' => $this->subject,
            'from_email' => $this->fromEmail,
            'to_recipients' => $this->toRecipients,
            'cc_recipients' => $this->ccRecipients,
            'received_at' => $this->receivedAtIso,
            'body_text' => $this->bodyText,
            'body_html' => $this->bodyHtml,
            'headers' => $this->headers,
            'attachments' => $this->attachments,
            'raw' => $this->raw,
        ];
    }
}
