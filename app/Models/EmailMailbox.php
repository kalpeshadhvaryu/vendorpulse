<?php

namespace App\Models;

use App\EmailMonitoring\Enums\MailboxDriver;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailMailbox extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization, SoftDeletes;

    protected $table = 'email_mailboxes';

    protected $fillable = [
        'organization_id',
        'name',
        'driver',
        'is_enabled',
        'connection_config',
        'sync_state',
        'last_polled_at',
        'last_successful_sync_at',
        'last_error',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'driver' => MailboxDriver::class,
            'is_enabled' => 'boolean',
            'connection_config' => 'array',
            'sync_state' => 'array',
            'last_polled_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }
}
