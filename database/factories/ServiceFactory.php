<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(25, 150) * 1000,
            'estimated_duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'image_path' => null,
            'is_active' => true,
        ];
    }
}
