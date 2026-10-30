<?php

namespace Database\Factories;

use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessHour> */
class BusinessHourFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['weekday' => fake()->unique()->numberBetween(0, 6), 'starts_at' => '09:00:00', 'ends_at' => '20:00:00', 'is_closed' => false];
    }
}
