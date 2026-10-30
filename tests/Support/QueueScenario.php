<?php

namespace Tests\Support;

use App\Enums\BarberAvailability;
use App\Enums\QueueSource;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BusinessHour;
use App\Models\BusinessSetting;
use App\Models\QrCode;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait QueueScenario
{
    private Barber $queueBarber;

    private Service $queueService;

    /** @return array{barber: Barber, service: Service, qr: QrCode} */
    protected function openQueue(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 10:00:00', 'Asia/Makassar'));
        BusinessSetting::query()->updateOrCreate(['key' => 'queue_registration_open'], ['value' => true]);
        BusinessHour::query()->updateOrCreate(['weekday' => 1], ['starts_at' => '08:00:00', 'ends_at' => '21:00:00', 'is_closed' => false]);
        $barber = Barber::factory()->create([
            'queue_prefix' => 'A', 'availability_status' => BarberAvailability::Available, 'accepts_queue' => true,
        ]);
        BarberSchedule::query()->create([
            'barber_id' => $barber->id, 'weekday' => 1, 'starts_at' => '09:00:00', 'ends_at' => '20:00:00', 'is_active' => true,
        ]);
        $service = Service::factory()->create(['price' => '50000.00', 'estimated_duration_minutes' => 30]);
        $qr = QrCode::query()->create(['code' => str_repeat('a', 64), 'label' => 'QR pengujian', 'is_active' => true]);
        $this->queueBarber = $barber;
        $this->queueService = $service;

        return compact('barber', 'service', 'qr');
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function queueInput(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Pelanggan Uji',
            'phone' => '0812'.random_int(10000000, 99999999),
            'barber_id' => $this->queueBarber->id,
            'service_id' => $this->queueService->id,
            'idempotency_key' => (string) Str::uuid(),
            'source' => QueueSource::Online,
        ], $overrides);
    }
}
