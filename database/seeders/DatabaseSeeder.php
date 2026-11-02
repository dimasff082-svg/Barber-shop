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
        // 1. Akun Admin Utama
        $admin1 = User::firstOrNew(['email' => 'admin@barberflow.com']);
        $admin1->name = 'Admin Barberflow';
        $admin1->password = Hash::make('password');
        $admin1->role = UserRole::Admin;
        $admin1->is_active = true;
        $admin1->email_verified_at = now();
        $admin1->save();

        // Akun Admin Tambahan (sesuai pengujian)
        $admin2 = User::firstOrNew(['email' => 'admin@gmail.com']);
        $admin2->name = 'Admin';
        $admin2->password = Hash::make('password');
        $admin2->role = UserRole::Admin;
        $admin2->is_active = true;
        $admin2->email_verified_at = now();
        $admin2->save();

        // 2. Akun Barber
        $barberUser = User::firstOrNew(['email' => 'barber@barberflow.com']);
        $barberUser->name = 'Barber Dimas';
        $barberUser->password = Hash::make('password');
        $barberUser->role = UserRole::Barber;
        $barberUser->is_active = true;
        $barberUser->email_verified_at = now();
        $barberUser->save();

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
