<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSpeedtestRun extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'organization_id',
        'created_by',
        'target_url',
        'checked_from',
        'final_url',
        'status_code',
        'success',
        'error',
        'timeout_seconds',
        'total_time_ms',
        'ttfb_ms',
        'dns_lookup_ms',
        'tcp_connect_ms',
        'tls_handshake_ms',
        'redirect_time_ms',
        'download_speed_kbps',
        'downloaded_bytes',
        'metrics',
        'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'status_code' => 'integer',
            'timeout_seconds' => 'integer',
            'downloaded_bytes' => 'integer',
            'total_time_ms' => 'float',
            'ttfb_ms' => 'float',
            'dns_lookup_ms' => 'float',
            'tcp_connect_ms' => 'float',
            'tls_handshake_ms' => 'float',
            'redirect_time_ms' => 'float',
            'download_speed_kbps' => 'float',
            'metrics' => 'array',
            'tested_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
