<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringLog extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'monitoring_check_id',
        'organization_id',
        'status',
        'http_status',
        'response_time_ms',
        'message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => MonitoringLogStatus::class,
            'meta' => 'array',
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
