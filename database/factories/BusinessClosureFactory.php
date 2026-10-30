<?php

namespace Database\Factories;

use App\Models\BusinessClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessClosure> */
class BusinessClosureFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['date' => fake()->unique()->date(), 'reason' => 'Hari libur'];
    }
}
