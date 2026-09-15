<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the first admin from ADMIN_EMAIL / ADMIN_PASSWORD when no admin
 * exists yet. In production prefer `php artisan make:filament-user`.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('is_admin', true)->exists()) {
            return;
        }

        $email = config('services.admin_email');
        $password = config('services.admin_password');

        if (! $email || ! $password) {
            $this->command?->warn('Skipping admin user: set ADMIN_EMAIL and ADMIN_PASSWORD, or run `php artisan make:filament-user`.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'password' => $password, 'is_admin' => true],
        );

        $this->command?->info("Admin user {$email} ready.");
    }
}
