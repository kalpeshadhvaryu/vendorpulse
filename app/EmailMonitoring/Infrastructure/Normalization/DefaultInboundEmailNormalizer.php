<?php

namespace App\EmailMonitoring\Infrastructure\Normalization;

use App\EmailMonitoring\Contracts\InboundEmailNormalizerInterface;
use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\EmailMonitoring\DTO\RawEmailEnvelope;

class DefaultInboundEmailNormalizer implements InboundEmailNormalizerInterface
{
    public function normalize(RawEmailEnvelope $envelope): NormalizedInboundEmail
    {
        return new NormalizedInboundEmail(
            externalMessageId: $envelope->externalMessageId,
            inReplyTo: $envelope->inReplyTo,
            subject: $envelope->subject,
            fromEmail: $envelope->fromEmail !== null ? strtolower(trim($envelope->fromEmail)) : null,
            toRecipients: array_values(array_filter(array_map(
                static fn (mixed $addr): string => is_string($addr) ? strtolower(trim($addr)) : '',
                $envelope->toRecipients
            ))),
            ccRecipients: array_values(array_filter(array_map(
                static fn (mixed $addr): string => is_string($addr) ? strtolower(trim($addr)) : '',
                $envelope->ccRecipients
            ))),
            receivedAtIso: $envelope->receivedAtIso,
            bodyText: $envelope->bodyText,
            bodyHtml: $envelope->bodyHtml,
            attachments: $envelope->attachments,
            meta: [
                'normalizer' => static::class,
                'header_keys' => array_keys($envelope->headers),
            ],
        );
    }
}
