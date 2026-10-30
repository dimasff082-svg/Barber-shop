<?php

namespace App\Policies;

use App\Models\Barber;
use App\Models\User;

class BarberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessStaffArea() && $user->isAdmin();
    }

    public function view(User $user, Barber $barber): bool
    {
        return $user->canAccessStaffArea()
            && ($user->isAdmin() || $barber->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->canAccessStaffArea() && $user->isAdmin();
    }

    public function update(User $user, Barber $barber): bool
    {
        return $user->canAccessStaffArea() && $user->isAdmin();
    }
}
