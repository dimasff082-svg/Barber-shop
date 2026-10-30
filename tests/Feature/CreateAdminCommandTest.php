<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_created_with_hashed_password_and_normalized_identity(): void
    {
        $password = 'Studio-Admin2026!';

        $this->artisan('barberflow:create-admin', [
            '--name' => '  Pemilik Studio  ',
            '--email' => '  OWNER@Example.com  ',
        ])
            ->expectsQuestion('Password admin', $password)
            ->expectsQuestion('Konfirmasi password admin', $password)
            ->expectsOutputToContain('berhasil dibuat')
            ->doesntExpectOutputToContain($password)
            ->assertSuccessful();

        $user = User::query()->sole();

        $this->assertSame('Pemilik Studio', $user->name);
        $this->assertSame('owner@example.com', $user->email);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_missing_name_and_email_are_prompted(): void
    {
        $this->artisan('barberflow:create-admin')
            ->expectsQuestion('Nama admin', 'Pemilik Studio')
            ->expectsQuestion('Email admin', 'owner@example.com')
            ->expectsQuestion('Password admin', 'Studio-Admin2026!')
            ->expectsQuestion('Konfirmasi password admin', 'Studio-Admin2026!')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'role' => 'admin', 'is_active' => true]);
    }

    public function test_weak_password_is_rejected_without_creating_an_account(): void
    {
        $this->artisan('barberflow:create-admin', ['--name' => 'Pemilik', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password admin', 'password')
            ->expectsQuestion('Konfirmasi password admin', 'password')
            ->expectsOutputToContain('Password admin tidak memenuhi ketentuan')
            ->doesntExpectOutputToContain('SQLSTATE')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_confirmation_must_match(): void
    {
        $password = 'Studio-Admin2026!';

        $this->artisan('barberflow:create-admin', ['--name' => 'Pemilik', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password admin', $password)
            ->expectsQuestion('Konfirmasi password admin', 'Berbeda-Admin2026!')
            ->expectsOutputToContain('Password admin tidak memenuhi ketentuan')
            ->doesntExpectOutputToContain($password)
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_account_is_never_promoted_or_given_a_new_password(): void
    {
        $user = User::factory()->inactive()->create(['name' => 'Barber Asli', 'email' => 'owner@example.com']);
        $passwordHash = $user->password;

        $this->artisan('barberflow:create-admin', ['--name' => 'Pemilik Baru', '--email' => 'owner@example.com'])
            ->expectsOutputToContain('Email sudah digunakan')
            ->assertFailed();

        $user->refresh();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Barber Asli', $user->name);
        $this->assertSame(UserRole::Barber, $user->role);
        $this->assertFalse($user->is_active);
        $this->assertSame($passwordHash, $user->password);
    }

    public function test_existing_email_is_checked_without_case_sensitivity(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'Owner@Example.COM']);
        $passwordHash = $user->password;

        $this->artisan('barberflow:create-admin', ['--name' => 'Pemilik Baru', '--email' => 'OWNER@example.com'])
            ->expectsOutputToContain('Email sudah digunakan')
            ->assertFailed();

        $user->refresh();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Owner@Example.COM', $user->email);
        $this->assertSame(UserRole::Barber, $user->role);
        $this->assertFalse($user->is_active);
        $this->assertSame($passwordHash, $user->password);
    }

    public function test_non_interactive_execution_fails_without_asking_for_secrets(): void
    {
        $this->artisan('barberflow:create-admin', [
            '--name' => 'Pemilik',
            '--email' => 'owner@example.com',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('harus dijalankan secara interaktif')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_email_is_rejected_before_asking_for_a_password(): void
    {
        $this->artisan('barberflow:create-admin', ['--name' => 'Pemilik', '--email' => 'bukan-email'])
            ->expectsOutputToContain('Data akun admin tidak valid')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_database_conflict_returns_a_safe_error_without_sql_or_secrets(): void
    {
        $password = 'Studio-Admin2026!';

        Event::listen('eloquent.creating: '.User::class, function () use ($password): void {
            throw new UniqueConstraintViolationException(
                'sqlite',
                'private SQL detail: insert into users (password) values (?)',
                [$password],
                new RuntimeException('SQLSTATE: duplicate email'),
            );
        });

        $this->artisan('barberflow:create-admin', ['--name' => 'Pemilik', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password admin', $password)
            ->expectsQuestion('Konfirmasi password admin', $password)
            ->expectsOutputToContain('Akun admin gagal dibuat')
            ->doesntExpectOutputToContain($password)
            ->doesntExpectOutputToContain('private SQL detail')
            ->doesntExpectOutputToContain('SQLSTATE')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_default_database_seeder_does_not_create_a_known_staff_account(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }
}
