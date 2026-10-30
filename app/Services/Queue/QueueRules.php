<?php

namespace App\Services\Queue;

use App\Enums\BarberAvailability;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessClosure;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\QueueTicket;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class QueueRules
{
    public function businessNow(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('barberflow.timezone', 'Asia/Makassar'));
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $setting = BusinessSetting::query()->find($key);

        return $setting === null ? $default : $setting->value;
    }

    public function graceMinutes(): int
    {
        $minutes = $this->setting('grace_minutes', 5);

        return is_int($minutes) && $minutes >= 1 && $minutes <= 120 ? $minutes : 5;
    }

    public function assertCanRegister(Barber $barber, Service $service, CarbonImmutable $now): void
    {
        if ($this->setting('queue_registration_open', false) !== true) {
            $this->reject('Pendaftaran antrean sedang ditutup.');
        }

        if (! $barber->is_active || ! $barber->accepts_queue || ! $barber->user->is_active
            || $barber->user->role !== UserRole::Barber
            || ! in_array($barber->availability_status, [BarberAvailability::Available, BarberAvailability::Busy], true)) {
            $this->reject('Barber sedang tidak menerima antrean.', 'barber_id');
        }

        if (! $service->is_active) {
            $this->reject('Layanan tidak tersedia.', 'service_id');
        }

        $date = $now->toDateString();
        $time = $now->format('H:i:s');
        $hours = BusinessHour::query()->where('weekday', $now->dayOfWeek)->first();
        if ($hours === null || $hours->is_closed || $hours->starts_at === null || $hours->ends_at === null
            || $time < $hours->starts_at || $time >= $hours->ends_at
            || BusinessClosure::query()->whereDate('date', $date)->exists()) {
            $this->reject('Barbershop sedang tutup.');
        }

        $schedule = BarberSchedule::query()->where('barber_id', $barber->id)->where('weekday', $now->dayOfWeek)->first();
        if ($schedule === null || ! $schedule->is_active || $time < $schedule->starts_at || $time >= $schedule->ends_at) {
            $this->reject('Barber berada di luar jadwal kerja.', 'barber_id');
        }

        $capacity = $barber->daily_capacity ?? $this->setting('daily_queue_capacity', 50);
        if (! is_int($capacity) || $capacity < 1 || $capacity > 32767) {
            $this->reject('Kapasitas antrean belum dikonfigurasi dengan benar.');
        }
        if (QueueTicket::query()->where('barber_id', $barber->id)->whereDate('queue_date', $date)->count() >= $capacity) {
            $this->reject('Kapasitas antrean barber hari ini sudah penuh.', 'barber_id');
        }
    }

    private function reject(string $message, string $field = 'queue'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
