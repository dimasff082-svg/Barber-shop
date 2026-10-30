<?php

namespace Database\Factories;

use App\Models\QrCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrCode> */
class QrCodeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => bin2hex(random_bytes(32)), 'label' => 'QR lokasi uji', 'is_active' => true];
    }
}
