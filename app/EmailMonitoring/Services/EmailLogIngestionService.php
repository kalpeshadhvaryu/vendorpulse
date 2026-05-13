<?php

namespace App\EmailMonitoring\Services;

use App\EmailMonitoring\DTO\RawEmailEnvelope;
use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\Models\EmailLog;
use Illuminate\Support\Carbon;

class EmailLogIngestionService
{
    public function ingestFromEnvelope(
        string $organizationId,
        ?string $emailMailboxId,
        RawEmailEnvelope $envelope,
    ): EmailLog {
        $receivedAt = $envelope->receivedAtIso
            ? Carbon::parse($envelope->receivedAtIso)
            : now();

        return EmailLog::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'email_mailbox_id' => $emailMailboxId,
                'external_message_id' => $envelope->externalMessageId,
            ],
            [
                'in_reply_to' => $envelope->inReplyTo,
                'subject' => $envelope->subject,
                'from_email' => $envelope->fromEmail,
                'to_recipients' => $envelope->toRecipients,
                'cc_recipients' => $envelope->ccRecipients,
                'received_at' => $receivedAt,
                'processing_status' => EmailLogProcessingStatus::Pending,
                'body_text' => $envelope->bodyText,
                'body_html' => $envelope->bodyHtml,
                'raw_envelope' => $envelope->toStorageArray(),
            ]
        );
    }
}
