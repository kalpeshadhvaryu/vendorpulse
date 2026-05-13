<?php

namespace App\EmailMonitoring\Infrastructure\Ocr;

use App\EmailMonitoring\Contracts\OcrProviderInterface;
use App\EmailMonitoring\DTO\AttachmentOcrContext;
use App\EmailMonitoring\DTO\OcrTextResult;

class NoopOcrProvider implements OcrProviderInterface
{
    public function isEnabled(): bool
    {
        return (bool) config('email-monitoring.ocr.enabled', false);
    }

    public function extractText(AttachmentOcrContext $context): OcrTextResult
    {
        return new OcrTextResult(
            text: null,
            confidence: 0.0,
            provider: 'noop',
            skipped: true,
            meta: ['reason' => 'OCR not configured — implement OcrProviderInterface.'],
        );
    }
}
