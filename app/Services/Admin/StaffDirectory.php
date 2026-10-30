<?php

namespace App\Services\Admin;

use App\Enums\BarberAvailability;
use App\Enums\QueueStatus;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;
use App\Services\StaffAudit;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffDirectory
{
    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?Barber $existing, ?string $photo): Barber
    {
        abort_unless($actor->canAccessStaffArea() && $actor->isAdmin(), 403);
        $barber = $existing === null ? new Barber : Barber::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
        if ($existing !== null) {
            $user = User::query()->whereKey($barber->user_id)->lockForUpdate()->firstOrFail();
            if (! $user->isBarber()) {
                throw ValidationException::withMessages(['email' => 'Akun tertaut bukan akun barber. Hubungi pengelola sistem.']);
            }
            if ($barber->queue_prefix !== $data['queue_prefix'] && ($barber->counters()->exists() || $barber->tickets()->exists())) {
                throw ValidationException::withMessages(['queue_prefix' => 'Prefix tidak dapat diubah setelah nomor antrean pernah diterbitkan.']);
            }
            if (! $data['is_active'] && $barber->tickets()->whereIn('status', [QueueStatus::Called->value, QueueStatus::InService->value])->exists()) {
                throw ValidationException::withMessages(['is_active' => 'Selesaikan atau batalkan secara administratif antrean yang diproses sebelum menonaktifkan barber.']);
            }
        } else {
            $user = new User;
            $user->role = UserRole::Barber;
            $barber->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(8));
            $barber->availability_status = BarberAvailability::OffDuty;
        }
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_active = (bool) $data['is_active'];
        if (is_string($data['password'] ?? null) && $data['password'] !== '') {
            $user->password = $data['password'];
            $user->remember_token = Str::random(60);
        }
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
        $barber->fill(collect($data)->only(['name', 'queue_prefix', 'specialization', 'bio', 'is_active', 'accepts_queue', 'daily_capacity'])->all());
        $barber->user_id = $user->id;
        if ($photo !== null || ($data['remove_image'] ?? false)) {
            $barber->photo = $photo;
        }
        $barber->save();
        app(StaffAudit::class)->record($actor, $existing === null ? 'barber.created' : 'barber.updated', 'barber', $barber->id, [
            'fields' => ['name', 'email', 'queue_prefix', 'specialization', 'bio', 'is_active', 'accepts_queue', 'daily_capacity'],
            'password_changed' => is_string($data['password'] ?? null) && $data['password'] !== '',
        ]);

        return $barber;
    }

    public function archive(User $actor, Barber $existing): void
    {
        abort_unless($actor->canAccessStaffArea() && $actor->isAdmin(), 403);
        $barber = Barber::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
        $user = User::query()->whereKey($barber->user_id)->lockForUpdate()->firstOrFail();
        if (! $user->isBarber()) {
            throw ValidationException::withMessages(['queue' => 'Akun ini tidak dapat dinonaktifkan melalui daftar barber.']);
        }
        if ($barber->tickets()->whereIn('status', [QueueStatus::Called->value, QueueStatus::InService->value])->exists()) {
            throw ValidationException::withMessages(['queue' => 'Selesaikan antrean yang sedang diproses terlebih dahulu.']);
        }
        $barber->update(['is_active' => false, 'accepts_queue' => false]);
        $user->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
        app(StaffAudit::class)->record($actor, 'barber.archived', 'barber', $barber->id);
    }
}
