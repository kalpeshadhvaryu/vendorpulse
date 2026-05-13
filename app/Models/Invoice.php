<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUuidPrimaryKey, ScopedToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'number',
        'status',
        'currency',
        'amount_cents',
        'tax_cents',
        'issued_on',
        'due_on',
        'paid_at',
        'description',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
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
