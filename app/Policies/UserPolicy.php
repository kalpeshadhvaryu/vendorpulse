<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewUser(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function updateUser(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function deleteUser(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function restoreUser(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }
}
