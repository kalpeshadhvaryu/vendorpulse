<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDeliverabilityTest extends Model
{
    use HasUuidPrimaryKey;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'token',
        'organization_id',
        'created_by',
        'source',
        'status',
        'requester_ip',
        'from_address',
        'subject',
        'score',
        'grade',
        'result',
        'expires_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'result' => 'array',
            'expires_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING && $this->expires_at->isFuture();
    }
}
