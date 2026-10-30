<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class CreateAdminCommand extends Command
{
    /** @var string */
    protected $signature = 'barberflow:create-admin
        {--name= : Nama admin baru}
        {--email= : Email admin baru}';

    /** @var string */
    protected $description = 'Membuat akun admin baru melalui input password tersembunyi';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Perintah ini harus dijalankan secara interaktif agar password dapat dimasukkan dengan aman.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Nama admin');
        $email = $this->option('email') ?? $this->ask('Email admin');

        $identityValidator = Validator::make([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($identityValidator->fails()) {
            $this->error('Data akun admin tidak valid.');

            foreach ($identityValidator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        /** @var array{name: string, email: string} $identity */
        $identity = $identityValidator->validated();

        try {
            if ($this->emailExists($identity['email'])) {
                return $this->duplicateEmail();
            }
        } catch (QueryException) {
            return $this->databaseFailure();
        }

        try {
            $password = $this->secret('Password admin', false);
            $confirmation = $this->secret('Konfirmasi password admin', false);
        } catch (RuntimeException) {
            $this->error('Terminal ini tidak mendukung input password tersembunyi. Gunakan terminal interaktif yang mendukungnya.');

            return self::FAILURE;
        }

        $passwordValidator = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);

        if ($passwordValidator->fails()) {
            $this->error('Password admin tidak memenuhi ketentuan. Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, angka, simbol, dan konfirmasi yang sama.');

            return self::FAILURE;
        }

        /** @var array{password: string} $credentials */
        $credentials = $passwordValidator->validated();

        try {
            $created = DB::transaction(function () use ($identity, $credentials): bool {
                if ($this->emailExists($identity['email'])) {
                    return false;
                }

                $user = new User;
                $user->name = $identity['name'];
                $user->email = $identity['email'];
                $user->password = $credentials['password'];
                $user->role = UserRole::Admin;
                $user->is_active = true;
                $user->save();

                return true;
            });
        } catch (QueryException) {
            return $this->databaseFailure();
        }

        if (! $created) {
            return $this->duplicateEmail();
        }

        $this->info("Admin {$identity['name']} ({$identity['email']}) berhasil dibuat.");

        return self::SUCCESS;
    }

    private function emailExists(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function duplicateEmail(): int
    {
        $this->error('Email sudah digunakan. Akun yang ada tidak diubah.');

        return self::FAILURE;
    }

    private function databaseFailure(): int
    {
        $this->error('Akun admin gagal dibuat. Periksa koneksi database, migrasi, dan kemungkinan email yang sudah digunakan.');

        return self::FAILURE;
    }
}
