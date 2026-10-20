<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * The development default; never acceptable in production.
     */
    public const DEFAULT_PASSWORD = 'password';

    /**
     * Create the default super-admin account from ADMIN_* env vars
     * (see config/permission.php).
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $admin */
        $admin = config('permission.default_admin');

        if (app()->isProduction() && $admin['password'] === self::DEFAULT_PASSWORD) {
            throw new RuntimeException('Set ADMIN_PASSWORD in .env before seeding the admin account in production.');
        }

        $user = User::firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole(Role::SUPER_ADMIN);
    }
}
