<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessStaffArea();
    }

    public function view(User $user, Service $service): bool
    {
        return $user->canAccessStaffArea();
    }

    public function create(User $user): bool
    {
        return $user->canAccessStaffArea() && $user->isAdmin();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->canAccessStaffArea() && $user->isAdmin();
    }
}
