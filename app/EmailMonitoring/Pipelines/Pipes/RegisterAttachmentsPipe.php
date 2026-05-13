<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\EmailMonitoring\Enums\AttachmentOcrStatus;
use App\EmailMonitoring\Support\VendorEmailAutomation;
use App\Models\EmailLog;
use App\Models\EmailLogAttachment;
use Closure;

class RegisterAttachmentsPipe
{
    public function handle(EmailLog $log, Closure $next): mixed
    {
        if (VendorEmailAutomation::shouldSkipDownstream($log)) {
            return $next($log);
        }

        $payload = $log->normalized_payload ?? [];

        if ($payload === []) {
            return $next($log);
        }

        $normalized = NormalizedInboundEmail::fromStoredArray($payload);

        foreach ($normalized->attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $filename = (string) ($attachment['filename'] ?? 'unnamed');
            $mime = isset($attachment['mime']) ? (string) $attachment['mime'] : null;
            $size = (int) ($attachment['size'] ?? 0);
            $contentId = isset($attachment['content_id']) ? (string) $attachment['content_id'] : null;
            $disk = (string) ($attachment['disk'] ?? 'local');
            $path = isset($attachment['raw_path']) ? (string) $attachment['raw_path'] : null;

            EmailLogAttachment::query()->updateOrCreate(
                [
                    'email_log_id' => $log->id,
                    'original_filename' => $filename,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                ],
                [
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'content_id' => $contentId,
                    'ocr_status' => AttachmentOcrStatus::Pending,
                    'meta' => [
                        'inline' => (bool) ($attachment['inline'] ?? false),
                        'source' => 'ingestion',
                    ],
                ]
            );
        }

        $log->update([
            'processing_meta' => array_merge($log->processing_meta ?? [], [
                'attachments_registered_at' => now()->toIso8601String(),
            ]),
        ]);

        return $next($log->fresh());
    }
}
