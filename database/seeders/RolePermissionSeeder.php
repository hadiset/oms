<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Create every application permission and the super-admin role.
     *
     * Safe to run repeatedly: existing records are left untouched.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        // No permissions attached: super-admins pass every check via Gate::before.
        Role::findOrCreate(Role::SUPER_ADMIN);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
