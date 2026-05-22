<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceMonitoringScreenshot extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    protected $fillable = [
        'experience_monitoring_run_id',
        'organization_id',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ExperienceMonitoringRun::class, 'experience_monitoring_run_id');
    }
}
