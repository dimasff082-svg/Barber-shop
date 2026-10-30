<?php

use App\Models\Barber;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('staff.admin', function (User $user): bool {
    $current = User::query()->find($user->id);

    return $current !== null && $current->is_active && $current->isAdmin();
});

Broadcast::channel('staff.barber.{barberId}', function (User $user, string $barberId): bool {
    $current = User::query()->find($user->id);

    return $current !== null && $current->is_active && $current->isBarber()
        && ctype_digit($barberId) && Barber::query()->whereKey((int) $barberId)
            ->where('user_id', $current->id)->where('is_active', true)->exists();
});
