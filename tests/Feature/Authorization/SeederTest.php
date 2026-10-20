<?php

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

test('creates every application permission and the super-admin role', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Permission::pluck('name')->all())->toEqualCanonicalizing(PermissionName::values())
        ->and(Role::where('name', Role::SUPER_ADMIN)->exists())->toBeTrue();
});

test('creates the default admin as super-admin from config', function () {
    config()->set('permission.default_admin', [
        'name' => 'Boss',
        'email' => 'boss@example.test',
        'password' => 'secret-pass',
    ]);

    $this->seed([RolePermissionSeeder::class, AdminUserSeeder::class]);

    $admin = User::where('email', 'boss@example.test')->firstOrFail();

    expect($admin->name)->toBe('Boss')
        ->and(Hash::check('secret-pass', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

test('the database seeder is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed([RolePermissionSeeder::class, AdminUserSeeder::class]);

    expect(Permission::count())->toBe(count(PermissionName::cases()))
        ->and(Role::where('name', Role::SUPER_ADMIN)->count())->toBe(1)
        ->and(User::where('email', config('permission.default_admin.email'))->count())->toBe(1);
});

test('refuses the default admin password in production', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('permission.default_admin.password', AdminUserSeeder::DEFAULT_PASSWORD);

    // Called directly: db:seed asks for confirmation in production.
    (new RolePermissionSeeder)->run();
    (new AdminUserSeeder)->run();
})->throws(RuntimeException::class, 'ADMIN_PASSWORD');

test('seeds the admin in production when a real password is set', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('permission.default_admin.password', 'a-real-secret');

    (new RolePermissionSeeder)->run();
    (new AdminUserSeeder)->run();

    expect(User::where('email', config('permission.default_admin.email'))->first()?->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

test('re-seeding does not overwrite an existing admin password', function () {
    $this->seed([RolePermissionSeeder::class, AdminUserSeeder::class]);
    $admin = User::where('email', config('permission.default_admin.email'))->firstOrFail();
    $admin->update(['password' => 'changed-by-admin']);

    config()->set('permission.default_admin.password', 'something-else');
    $this->seed(AdminUserSeeder::class);

    expect(Hash::check('changed-by-admin', $admin->fresh()->password))->toBeTrue();
});
