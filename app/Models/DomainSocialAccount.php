<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainSocialAccount extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    protected $table = 'domain_social_accounts';

    public const CREATED_AT = null;

    protected $fillable = [
        'monitoring_check_id',
        'organization_id',
        'platform_name',
        'social_handle_or_url',
        'last_follower_count',
    ];

    protected function casts(): array
    {
        return [
            'last_follower_count' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function monitoringCheck(): BelongsTo
    {
        return $this->belongsTo(MonitoringCheck::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
