<?php

namespace App\Models;

use App\EmailMonitoring\Enums\AttachmentOcrStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLogAttachment extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'email_log_attachments';

    protected $fillable = [
        'email_log_id',
        'original_filename',
        'mime_type',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'content_id',
        'sha256',
        'ocr_status',
        'ocr_text',
        'ocr_meta',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ocr_status' => AttachmentOcrStatus::class,
            'ocr_meta' => 'array',
            'meta' => 'array',
        ];
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
