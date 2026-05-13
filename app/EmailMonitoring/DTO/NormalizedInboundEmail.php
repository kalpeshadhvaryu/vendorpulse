<?php

namespace App\EmailMonitoring\DTO;

/**
 * Canonical shape used by matching + invoice extraction stages.
 *
 * @param  array<int, array<string, mixed>>  $attachments
 * @param  array<string, mixed>  $meta
 */
final readonly class NormalizedInboundEmail
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
        public array $attachments,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
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
            'attachments' => $this->attachments,
            'meta' => $this->meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromStoredArray(array $payload): self
    {
        return new self(
            externalMessageId: (string) ($payload['external_message_id'] ?? ''),
            inReplyTo: isset($payload['in_reply_to']) ? (string) $payload['in_reply_to'] : null,
            subject: isset($payload['subject']) ? (string) $payload['subject'] : null,
            fromEmail: isset($payload['from_email']) ? (string) $payload['from_email'] : null,
            toRecipients: (array) ($payload['to_recipients'] ?? []),
            ccRecipients: (array) ($payload['cc_recipients'] ?? []),
            receivedAtIso: isset($payload['received_at']) ? (string) $payload['received_at'] : null,
            bodyText: isset($payload['body_text']) ? (string) $payload['body_text'] : null,
            bodyHtml: isset($payload['body_html']) ? (string) $payload['body_html'] : null,
            attachments: (array) ($payload['attachments'] ?? []),
            meta: (array) ($payload['meta'] ?? []),
        );
    }
}
