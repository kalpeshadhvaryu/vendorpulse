<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceMonitoringExecutionLog extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    protected $fillable = [
        'experience_monitoring_run_id',
        'organization_id',
        'source',
        'level',
        'message',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ExperienceMonitoringRun::class, 'experience_monitoring_run_id');
    }
}
