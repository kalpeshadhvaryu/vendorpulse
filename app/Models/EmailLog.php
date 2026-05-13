<?php

namespace App\Models;

use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailLog extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization, SoftDeletes;

    protected $table = 'email_logs';

    protected $fillable = [
        'organization_id',
        'email_mailbox_id',
        'external_message_id',
        'in_reply_to',
        'subject',
        'from_email',
        'to_recipients',
        'cc_recipients',
        'received_at',
        'processing_status',
        'vendor_id',
        'vendor_email_id',
        'vendor_match_confidence',
        'body_text',
        'body_html',
        'raw_envelope',
        'normalized_payload',
        'processing_meta',
        'failure_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'processing_status' => EmailLogProcessingStatus::class,
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'received_at' => 'datetime',
            'vendor_match_confidence' => 'decimal:4',
            'raw_envelope' => 'array',
            'normalized_payload' => 'array',
            'processing_meta' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(EmailMailbox::class, 'email_mailbox_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorEmail(): BelongsTo
    {
        return $this->belongsTo(VendorEmail::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailLogAttachment::class);
    }

    public function invoiceExtractions(): HasMany
    {
        return $this->hasMany(EmailInvoiceExtraction::class);
    }
}
