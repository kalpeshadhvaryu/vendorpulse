<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceMonitoringRunMetric extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    protected $fillable = [
        'experience_monitoring_run_id',
        'organization_id',
        'metric_key',
        'metric_value',
        'unit',
        'meta',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_value' => 'float',
            'meta' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ExperienceMonitoringRun::class, 'experience_monitoring_run_id');
    }
}
