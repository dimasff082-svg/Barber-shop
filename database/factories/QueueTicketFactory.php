<?php

namespace Database\Factories;

use App\Enums\QueueSource;
use App\Enums\QueueStatus;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\QueueTicket;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QueueTicket> */
class QueueTicketFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'barber_id' => Barber::factory(),
            'service_id' => Service::factory(),
            'customer_name' => fn (array $attributes) => Customer::query()->whereKey($attributes['customer_id'])->firstOrFail()->name,
            'normalized_phone' => fn (array $attributes) => Customer::query()->whereKey($attributes['customer_id'])->firstOrFail()->normalized_phone,
            'queue_date' => now()->toDateString(),
            'sequence_number' => 1,
            'queue_number' => fn (array $attributes) => Barber::query()->whereKey($attributes['barber_id'])->firstOrFail()->queue_prefix.'-'.str_pad((string) $attributes['sequence_number'], 3, '0', STR_PAD_LEFT),
            'source' => QueueSource::Online,
            'status' => QueueStatus::WaitingCheckin,
            'token_hash' => hash('sha256', random_bytes(32)),
            'owner_hash' => hash('sha256', random_bytes(32)),
            'request_fingerprint' => hash('sha256', random_bytes(32)),
            'idempotency_key' => fake()->uuid(),
            'price_snapshot' => fn (array $attributes) => Service::query()->whereKey($attributes['service_id'])->firstOrFail()->price,
            'duration_snapshot' => fn (array $attributes) => Service::query()->whereKey($attributes['service_id'])->firstOrFail()->estimated_duration_minutes,
        ];
    }
}
