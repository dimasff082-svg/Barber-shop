<?php

namespace Database\Factories;

use App\Models\Barber;
use App\Models\QueueCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QueueCounter> */
class QueueCounterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['barber_id' => Barber::factory(), 'queue_date' => now()->toDateString(), 'last_number' => 0];
    }
}
