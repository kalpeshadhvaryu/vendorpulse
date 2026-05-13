<?php

namespace App\Models;

use App\EmailMonitoring\Enums\InvoiceExtractionStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailInvoiceExtraction extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    protected $table = 'email_invoice_extractions';

    protected $fillable = [
        'email_log_id',
        'organization_id',
        'vendor_id',
        'pipeline_driver',
        'status',
        'extracted_payload',
        'field_confidence_scores',
        'aggregate_confidence',
        'processing_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceExtractionStatus::class,
            'extracted_payload' => 'array',
            'field_confidence_scores' => 'array',
            'aggregate_confidence' => 'decimal:4',
            'processing_notes' => 'array',
        ];
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
