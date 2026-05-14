<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'is_admin',              // Added for Global Admin
    'timezone',
    'default_organization_id',
    'email_verified_at',
    'created_by',
    'updated_by',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuidPrimaryKey, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean', // Ensures 1/0 becomes true/false
        ];
    }

    public function defaultOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'default_organization_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'meta'])
            ->withTimestamps();
    }

    public function belongsToOrganization(string $organizationId): bool
    {
        return $this->organizations()->where('organizations.id', $organizationId)->exists();
    }

    public function hasOrganizationRole(string $role): bool
    {
        return $this->organizations()->wherePivot('role', $role)->exists();
    }

    /**
     * Determine if the user is a Global Admin.
     */
    public function isAdmin(): bool
    {
        // Now checks the specific column instead of organization roles
        return (bool) $this->is_admin;
    }
}