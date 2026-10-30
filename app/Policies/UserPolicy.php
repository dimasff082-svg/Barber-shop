<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $actor, User $user): bool
    {
        return $actor->canAccessStaffArea() && $actor->is($user);
    }

    public function delete(User $actor, User $user): bool
    {
        // Staff lifecycle uses administrative deactivation, preserving audit history.
        return false;
    }
}
