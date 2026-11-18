<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoKeyword extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'organization_id',
        'created_by',
        'phrase',
        'priority',
        'target_url',
        'target_domain',
        'country',
        'language',
        'device',
        'tags',
        'notes',
        'status',
        'current_google_position',
        'current_google_url',
        'previous_google_position',
        'current_ai_cited',
        'last_checked_at',
        'next_check_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'current_ai_cited' => 'boolean',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'current_google_position' => 'integer',
            'previous_google_position' => 'integer',
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

    public function checks(): HasMany
    {
        return $this->hasMany(SeoKeywordCheck::class);
    }

    public function positionChange(): ?int
    {
        if ($this->current_google_position === null || $this->previous_google_position === null) {
            return null;
        }

        // Lower position number is better; positive = improved
        return $this->previous_google_position - $this->current_google_position;
    }
}
