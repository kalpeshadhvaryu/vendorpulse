<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudflareZone extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'cf_zone_id',
        'name',
        'status',
        'paused',
        'plan_name',
        'name_servers',
        'ssl_mode',
        'ssl_status',
        'meta',
        'organization_id',
        'assigned_by',
        'assigned_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'paused' => 'boolean',
            'name_servers' => 'array',
            'meta' => 'array',
            'assigned_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
