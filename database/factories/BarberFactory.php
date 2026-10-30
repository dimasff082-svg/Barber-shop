<?php

namespace Database\Factories;

use App\Enums\BarberAvailability;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Barber> */
class BarberFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_id' => User::factory()->state(['role' => UserRole::Barber, 'is_active' => true]),
            'name' => $name,
            'slug' => fake()->unique()->slug(3),
            'photo' => null,
            'bio' => null,
            'specialization' => null,
            'queue_prefix' => fake()->unique()->regexify('[A-Z]{3}'),
            'availability_status' => BarberAvailability::OffDuty,
            'is_active' => true,
            'accepts_queue' => false,
            'daily_capacity' => null,
        ];
    }
}
