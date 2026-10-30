<?php

namespace Tests\Feature;

use App\Enums\BarberAvailability;
use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_accounts_default_to_inactive_barbers_without_elevated_access(): void
    {
        $user = User::query()->forceCreate([
            'name' => 'Pending staff',
            'email' => 'pending@example.test',
            'password' => 'test-password-only',
        ])->refresh();

        $this->assertSame(UserRole::Barber, $user->role);
        $this->assertFalse($user->is_active);
    }

    public function test_foundation_models_cast_domain_values_and_link_a_barber_to_the_staff_account(): void
    {
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['price' => '45000.50', 'estimated_duration_minutes' => 30]);

        $this->assertSame(UserRole::Barber, $barber->user->role);
        $this->assertTrue($barber->user->is_active);
        $this->assertSame(BarberAvailability::OffDuty, $barber->availability_status);
        $this->assertTrue($barber->is_active);
        $this->assertSame('45000.50', $service->price);
        $this->assertSame(30, $service->estimated_duration_minutes);
        $this->assertTrue($service->is_active);
    }

    public function test_barber_profiles_and_services_default_to_inactive_and_accept_minimum_service_values(): void
    {
        $user = User::factory()->create();
        $barber = Barber::query()->create([
            'user_id' => $user->id,
            'name' => 'Pending barber',
            'slug' => 'pending-barber',
            'queue_prefix' => 'A',
        ])->refresh();
        $service = Service::query()->create([
            'name' => 'Complimentary finishing',
            'slug' => 'complimentary-finishing',
            'price' => '0.00',
            'estimated_duration_minutes' => 1,
        ])->refresh();

        $this->assertFalse($barber->is_active);
        $this->assertSame(BarberAvailability::OffDuty, $barber->availability_status);
        $this->assertFalse($service->is_active);
        $this->assertSame('0.00', $service->price);
        $this->assertSame(1, $service->estimated_duration_minutes);
    }

    public function test_service_values_at_the_database_upper_boundaries_are_accepted(): void
    {
        $service = Service::factory()->create([
            'price' => '9999999999.99',
            'estimated_duration_minutes' => 32767,
        ])->refresh();

        $this->assertSame('9999999999.99', $service->price);
        $this->assertSame(32767, $service->estimated_duration_minutes);
    }

    public function test_a_staff_account_can_have_only_one_barber_profile(): void
    {
        $barber = Barber::factory()->create();

        $this->expectException(QueryException::class);

        Barber::factory()->create(['user_id' => $barber->user_id]);
    }

    public function test_a_queue_prefix_cannot_be_shared_by_two_barbers(): void
    {
        Barber::factory()->create(['queue_prefix' => 'A']);

        $this->expectException(QueryException::class);

        Barber::factory()->create(['queue_prefix' => 'A']);
    }

    public function test_staff_accounts_referenced_by_a_barber_cannot_be_deleted(): void
    {
        $barber = Barber::factory()->create();

        $this->expectException(QueryException::class);

        $barber->user->delete();
    }

    public function test_the_database_rejects_unknown_staff_roles(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->update(['role' => 'customer']);
    }

    public function test_the_database_rejects_unknown_barber_availability(): void
    {
        $barber = Barber::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('barbers')->where('id', $barber->id)->update(['availability_status' => 'unknown']);
    }

    #[DataProvider('invalidPrefixes')]
    public function test_the_database_rejects_invalid_queue_prefixes(string $prefix): void
    {
        $this->expectException(QueryException::class);

        Barber::factory()->create(['queue_prefix' => $prefix]);
    }

    public static function invalidPrefixes(): array
    {
        return [[''], ['a'], ['A1'], ['ABCD']];
    }

    #[DataProvider('invalidServiceValues')]
    public function test_the_database_rejects_invalid_service_values(int $price, int $duration): void
    {
        $this->expectException(QueryException::class);

        Service::factory()->create(['price' => $price, 'estimated_duration_minutes' => $duration]);
    }

    public static function invalidServiceValues(): array
    {
        return [[-1, 30], [50000, 0], [50000, -1], [10000000000, 30], [50000, 32768]];
    }

    public function test_database_guards_also_apply_when_existing_records_are_updated(): void
    {
        $service = Service::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('services')->where('id', $service->id)->update(['price' => -1]);
    }

    public function test_the_database_rejects_zero_duration_on_updates(): void
    {
        $service = Service::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('services')->where('id', $service->id)->update(['estimated_duration_minutes' => 0]);
    }

    public function test_the_database_rejects_an_invalid_prefix_on_updates(): void
    {
        $barber = Barber::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('barbers')->where('id', $barber->id)->update(['queue_prefix' => 'bad']);
    }
}
