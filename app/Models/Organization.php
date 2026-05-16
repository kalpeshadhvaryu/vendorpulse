<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'settings',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name).'-'.Str::lower(Str::random(6));
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'meta'])
            ->withTimestamps();
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class, 'company_id');
    }

    public function emailMailboxes(): HasMany
    {
        return $this->hasMany(EmailMailbox::class, 'organization_id');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'organization_id');
    }

    public function monitoringChecks(): HasMany
    {
        return $this->hasMany(MonitoringCheck::class, 'organization_id');
    }

    public function monitoringLogs(): HasMany
    {
        return $this->hasMany(MonitoringLog::class, 'organization_id');
    }

    public function websiteSpeedtestRuns(): HasMany
    {
        return $this->hasMany(WebsiteSpeedtestRun::class, 'organization_id');
    }
}
