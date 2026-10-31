<?php

namespace Database\Seeders;

use App\Enums\BarberAvailability;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with default login credentials.
     */
    public function run(): void
    {
        // 1. Akun Admin
        User::updateOrCreate(
            ['email' => 'admin@barberflow.com'],
            [
                'name' => 'Admin Barberflow',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Akun Barber
        $barberUser = User::updateOrCreate(
            ['email' => 'barber@barberflow.com'],
            [
                'name' => 'Barber Dimas',
                'password' => Hash::make('password'),
                'role' => UserRole::Barber,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Barber::updateOrCreate(
            ['user_id' => $barberUser->id],
            [
                'name' => 'Barber Dimas',
                'slug' => 'barber-dimas',
                'specialization' => 'Classic Cut & Fade Specialist',
                'bio' => 'Barber berpengalaman dengan keahlian potongan modern dan klasik.',
                'queue_prefix' => 'BDM',
                'availability_status' => BarberAvailability::Available,
                'is_active' => true,
                'accepts_queue' => true,
                'daily_capacity' => 30,
            ]
        );
    }
}
