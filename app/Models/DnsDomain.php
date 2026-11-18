<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DnsDomain extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    public const PROVIDER_CLOUDFLARE = 'cloudflare';

    public const PROVIDER_MANUAL = 'manual';

    protected $fillable = [
        'organization_id',
        'name',
        'provider',
        'cloudflare_zone_id',
        'notes',
        'created_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function cloudflareZone(): BelongsTo
    {
        return $this->belongsTo(CloudflareZone::class, 'cloudflare_zone_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    public function isCloudflare(): bool
    {
        return $this->provider === self::PROVIDER_CLOUDFLARE;
    }

    public function isManual(): bool
    {
        return $this->provider === self::PROVIDER_MANUAL;
    }
}
