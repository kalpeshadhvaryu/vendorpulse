<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Database\Factories\MonitoringCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonitoringCheck extends Model
{
    /** @use HasFactory<MonitoringCheckFactory> */
    use HasFactory, HasUuidPrimaryKey, ScopedToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'name',
        'type',
        'endpoint',
        'configuration',
        'interval_seconds',
        'enabled',
        'last_status',
        'last_message',
        'last_http_status',
        'last_response_time_ms',
        'consecutive_failures',
        'last_run_at',
        'next_run_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_http_status' => 'integer',
            'last_response_time_ms' => 'integer',
            'consecutive_failures' => 'integer',
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

    public function monitoringLogs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class);
    }
}
