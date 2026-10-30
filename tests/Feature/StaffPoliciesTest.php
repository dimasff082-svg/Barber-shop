<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_barbers_can_view_only_their_own_staff_profile(): void
    {
        $own = Barber::factory()->create();
        $other = Barber::factory()->create();

        $this->assertTrue($own->user->can('view', $own));
        $this->assertFalse($own->user->can('view', $other));
        $this->assertFalse($own->user->can('update', $own));
        $this->assertFalse($own->user->can('create', Barber::class));
        $this->assertFalse($own->user->can('viewAny', Barber::class));
    }

    public function test_only_active_admins_can_manage_barbers_and_services(): void
    {
        $admin = User::factory()->admin()->create();
        $barber = Barber::factory()->create();
        $service = Service::factory()->create();

        $this->assertTrue($admin->can('viewAny', Barber::class));
        $this->assertTrue($admin->can('create', Barber::class));
        $this->assertTrue($admin->can('update', $barber));
        $this->assertTrue($admin->can('create', Service::class));
        $this->assertTrue($admin->can('update', $service));
        $this->assertFalse($barber->user->can('create', Service::class));
        $this->assertFalse($barber->user->can('update', $service));

        $admin->is_active = false;
        $admin->save();
        $this->assertFalse($admin->can('update', $barber));
        $this->assertFalse($admin->can('update', $service));
    }

    public function test_user_role_and_activation_are_not_mass_assignable(): void
    {
        $user = User::factory()->barber()->create();
        $user->fill(['role' => 'admin', 'is_active' => false])->save();

        $this->assertTrue($user->refresh()->isBarber());
        $this->assertTrue($user->is_active);
    }
}
