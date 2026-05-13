<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\Contracts\OcrProviderInterface;
use App\EmailMonitoring\DTO\AttachmentOcrContext;
use App\EmailMonitoring\Enums\AttachmentOcrStatus;
use App\EmailMonitoring\Support\VendorEmailAutomation;
use App\Models\EmailLog;
use Closure;

class RunOcrPlaceholderPipe
{
    public function __construct(
        protected OcrProviderInterface $ocr,
    ) {}

    public function handle(EmailLog $log, Closure $next): mixed
    {
        if (VendorEmailAutomation::shouldSkipDownstream($log)) {
            return $next($log);
        }

        foreach ($log->attachments as $attachment) {
            if (! $this->ocr->isEnabled()) {
                $attachment->update([
                    'ocr_status' => AttachmentOcrStatus::Skipped,
                    'ocr_meta' => ['reason' => 'OCR disabled in configuration.'],
                ]);

                continue;
            }

            if ($attachment->storage_path === null || $attachment->storage_path === '') {
                $attachment->update([
                    'ocr_status' => AttachmentOcrStatus::Skipped,
                    'ocr_meta' => ['reason' => 'No binary persisted yet — wire storage in ingestion.'],
                ]);

                continue;
            }

            $result = $this->ocr->extractText(new AttachmentOcrContext(
                attachmentId: (string) $attachment->id,
                disk: $attachment->storage_disk,
                path: (string) $attachment->storage_path,
                mimeType: (string) ($attachment->mime_type ?? 'application/octet-stream'),
            ));

            $attachment->update([
                'ocr_status' => $result->skipped ? AttachmentOcrStatus::Skipped : AttachmentOcrStatus::Completed,
                'ocr_text' => $result->text,
                'ocr_meta' => array_merge($attachment->ocr_meta ?? [], $result->meta, [
                    'confidence' => $result->confidence,
                    'provider' => $result->provider,
                ]),
            ]);
        }

        return $next($log->fresh());
    }
}
