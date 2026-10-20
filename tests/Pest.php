<?php

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions())
    ->in('Feature', '../resources/views');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create every application permission and the super-admin role.
 */
function seedPermissions(): void
{
    (new RolePermissionSeeder)->run();
}

/**
 * Create a user holding exactly the given permissions (via a dedicated role).
 */
function userWithPermissions(PermissionName ...$permissions): User
{
    seedPermissions();

    $role = Role::create(['name' => 'role-'.Str::lower(Str::random(8))]);
    $role->syncPermissions(array_map(fn (PermissionName $p) => $p->value, $permissions));

    return User::factory()->create()->assignRole($role);
}

function superAdmin(): User
{
    seedPermissions();

    return User::factory()->create()->assignRole(Role::SUPER_ADMIN);
}
