<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\Contracts\InboundEmailNormalizerInterface;
use App\EmailMonitoring\DTO\RawEmailEnvelope;
use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\Models\EmailLog;
use Closure;

class NormalizeInboundEmailPipe
{
    public function __construct(
        protected InboundEmailNormalizerInterface $normalizer,
    ) {}

    public function handle(EmailLog $log, Closure $next): mixed
    {
        $raw = $log->raw_envelope ?? [];

        $envelope = RawEmailEnvelope::fromArray($raw);
        $normalized = $this->normalizer->normalize($envelope);

        $log->update([
            'subject' => $normalized->subject ?? $log->subject,
            'from_email' => $normalized->fromEmail ?? $log->from_email,
            'to_recipients' => $normalized->toRecipients,
            'cc_recipients' => $normalized->ccRecipients,
            'body_text' => $normalized->bodyText ?? $log->body_text,
            'body_html' => $normalized->bodyHtml ?? $log->body_html,
            'normalized_payload' => $normalized->toArray(),
            'processing_status' => EmailLogProcessingStatus::Normalized,
            'processing_meta' => array_merge($log->processing_meta ?? [], [
                'normalized_at' => now()->toIso8601String(),
            ]),
        ]);

        return $next($log->fresh());
    }
}
