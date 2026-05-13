<?php

namespace App\EmailMonitoring\DTO;

final readonly class AttachmentOcrContext
{
    public function __construct(
        public string $attachmentId,
        public string $disk,
        public string $path,
        public string $mimeType,
    ) {}
}
