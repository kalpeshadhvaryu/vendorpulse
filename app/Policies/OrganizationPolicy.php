<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }

    public function attachMember(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }

    public function createUser(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }

    public function viewUsers(User $user): bool
    {
        return $user->isAdmin();
    }

    public function manageSmtp(User $user, Organization $organization): bool
    {
        return $user->isAdmin()
            || $user->hasOrganizationRoleInOrganization((string) $organization->id, ['owner', 'admin']);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }
}