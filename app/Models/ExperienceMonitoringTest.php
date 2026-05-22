<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExperienceMonitoringTest extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'login_url',
        'login_username',
        'login_password',
        'dashboard_url',
        'interval_seconds',
        'browser_type',
        'timeout_ms',
        'concurrent_sessions',
        'configuration',
        'thresholds',
        'enabled',
        'last_status',
        'last_error',
        'last_run_at',
        'next_run_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'login_password' => 'encrypted',
            'interval_seconds' => 'integer',
            'timeout_ms' => 'integer',
            'concurrent_sessions' => 'integer',
            'configuration' => 'array',
            'thresholds' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ExperienceMonitoringRun::class, 'experience_monitoring_test_id');
    }
}
