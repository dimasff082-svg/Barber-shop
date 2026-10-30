<?php

namespace App\Services\Queue;

use App\Enums\BarberAvailability;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\StaffAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarberAvailabilityService
{
    public function change(User $actor, int $barberId, BarberAvailability|string $availability): Barber
    {
        $status = is_string($availability) ? BarberAvailability::tryFrom($availability) : $availability;
        if ($status === null) {
            throw ValidationException::withMessages(['availability' => 'Status ketersediaan tidak valid.']);
        }

        return DB::transaction(function () use ($actor, $barberId, $status) {
            $barber = Barber::query()->lockForUpdate()->findOrFail($barberId);
            $current = User::query()->find($actor->id);
            if ($current === null || ! $current->canAccessStaffArea()
                || (! $current->isAdmin() && (! $current->isBarber() || $current->id !== $barber->user_id || ! $barber->is_active))) {
                throw new AuthorizationException('Anda tidak memiliki akses ke barber ini.');
            }
            $processing = QueueTicket::query()->where('barber_id', $barber->id)
                ->whereIn('status', [QueueStatus::Called->value, QueueStatus::InService->value])->lockForUpdate()->first();
            if (($processing !== null && $status !== BarberAvailability::Busy)
                || ($status === BarberAvailability::Busy && $processing?->status !== QueueStatus::InService)) {
                throw ValidationException::withMessages(['availability' => 'Ketersediaan tidak dapat memutus antrean yang sedang diproses.']);
            }
            $previous = $barber->availability_status;
            $barber->forceFill(['availability_status' => $status])->save();
            if ($previous !== $status) {
                app(StaffAudit::class)->record($current, 'barber.availability_changed', 'barber', $barber->id, [
                    'previous' => $previous->value, 'availability' => $status->value,
                ]);
            }

            return $barber;
        }, 5);
    }
}
