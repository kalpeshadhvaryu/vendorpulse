<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToCompany;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, HasUuidPrimaryKey, ScopedToCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'vendor_type',
        'billing_email',
        'support_email',
        'website',
        'currency',
        'expected_amount',
        'billing_cycle',
        'renewal_date',
        'auto_detect_invoices',
        'auto_fetch_email',
        'match_inbound_from_website_domain',
        'monitoring_enabled',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'vendor_type' => VendorType::class,
            'billing_cycle' => BillingCycle::class,
            'status' => VendorStatus::class,
            'renewal_date' => 'date',
            'expected_amount' => 'decimal:2',
            'auto_detect_invoices' => 'boolean',
            'auto_fetch_email' => 'boolean',
            'match_inbound_from_website_domain' => 'boolean',
            'monitoring_enabled' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'company_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(VendorEmail::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function monitoringChecks(): HasMany
    {
        return $this->hasMany(MonitoringCheck::class);
    }
}
