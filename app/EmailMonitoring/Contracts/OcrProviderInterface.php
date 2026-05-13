<?php

namespace App\EmailMonitoring\Contracts;

use App\EmailMonitoring\DTO\AttachmentOcrContext;
use App\EmailMonitoring\DTO\OcrTextResult;

/**
 * OCR integration point — implement with Tesseract, Textract, Google Vision, etc.
 * Default binding is a no-op implementation.
 */
interface OcrProviderInterface
{
    public function isEnabled(): bool;

    public function extractText(AttachmentOcrContext $context): OcrTextResult;
}
