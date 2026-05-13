<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Database\Factories\VendorEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorEmail extends Model
{
    /** @use HasFactory<VendorEmailFactory> */
    use HasFactory, HasUuidPrimaryKey, ScopedToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'email',
        'label',
        'purpose',
        'is_monitored',
        'last_checked_at',
        'last_check_status',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_monitored' => 'boolean',
            'last_checked_at' => 'datetime',
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
