<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $user = $this->staff(UserRole::Admin);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_barber_login_ignores_an_admin_intended_destination(): void
    {
        $user = $this->staff(UserRole::Barber);

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('barber.dashboard'))
            ->assertSessionMissing('url.intended');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_json_response_preserves_the_fortify_contract(): void
    {
        $user = $this->staff(UserRole::Admin);

        $this->postJson(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertExactJson(['two_factor' => false]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_staff_cannot_log_in_even_with_a_valid_password(): void
    {
        $user = $this->staff(UserRole::Admin, false);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_session_is_revoked_on_the_public_home_page(): void
    {
        $user = $this->staff(UserRole::Barber);
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->withSession(['previous_staff_data' => 'private'])
            ->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('previous_staff_data');

        $this->assertGuest();
    }

    public function test_inactive_staff_cannot_retain_a_session_on_settings(): void
    {
        $user = $this->staff(UserRole::Admin, false);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_staff_json_request_is_unauthenticated(): void
    {
        $user = $this->staff(UserRole::Admin, false);

        $this->actingAs($user)
            ->getJson(route('admin.dashboard'))
            ->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_admin_cannot_access_barber_dashboard(): void
    {
        $this->actingAs($this->staff(UserRole::Admin))
            ->get(route('barber.dashboard'))
            ->assertForbidden();
    }

    public function test_barber_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->staff(UserRole::Barber))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_each_role_can_access_its_own_dashboard(): void
    {
        $this->actingAs($this->staff(UserRole::Admin))
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($this->staff(UserRole::Barber))
            ->get(route('barber.dashboard'))
            ->assertOk();
    }

    public function test_guests_are_redirected_from_both_staff_dashboards(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('barber.dashboard'))->assertRedirect(route('login'));
    }

    public function test_public_registration_is_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Unapproved Staff',
            'email' => 'unapproved@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'unapproved@example.test']);
    }

    public function test_staff_passwords_are_rehashed_when_required_on_login(): void
    {
        $user = $this->staff(UserRole::Barber);
        $previousHash = Hash::make('password', ['rounds' => 5]);
        // Simulate a persisted legacy hash without weakening the hashed cast.
        DB::table('users')->where('id', $user->id)->update(['password' => $previousHash]);
        $user->refresh();
        $this->assertTrue(Hash::needsRehash($previousHash));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('barber.dashboard'));

        $this->assertNotSame($previousHash, $user->refresh()->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_password_reset_revokes_an_existing_staff_session(): void
    {
        $user = $this->staff(UserRole::Admin);
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $previousSessionHash = session('password_hash_web');
        $this->assertNotNull($previousSessionHash);
        $this->post(route('logout'))->assertRedirect(route('home'));

        $newPassword = 'Studio-Reset2026!';
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check($newPassword, $user->refresh()->password));

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $previousSessionHash])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_password_change_preserves_own_session_and_rejects_a_stale_session(): void
    {
        $user = $this->staff(UserRole::Barber);
        $this->actingAs($user)->get(route('barber.dashboard'))->assertOk();
        $previousSessionHash = session('password_hash_web');
        $this->assertNotNull($previousSessionHash);
        $newPassword = 'Studio-Change2026!';

        $this->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->get(route('barber.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check($newPassword, $user->refresh()->password));

        $this->withSession(['password_hash_web' => $previousSessionHash])
            ->get(route('barber.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function staff(UserRole $role, bool $active = true): User
    {
        $user = User::factory()->make();
        $user->forceFill(['role' => $role, 'is_active' => $active])->save();

        return $user;
    }
}
