<?php

use App\Enums\PermissionName;
use App\Models\User;

dataset('management pages', [
    'permission' => ['permission', PermissionName::PermissionView],
    'role' => ['role', PermissionName::RoleView],
    'user' => ['user', PermissionName::UserView],
]);

test('guests are redirected to login', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('management pages');

test('users without the view permission get 403', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertForbidden();
})->with('management pages');

test('users with the view permission can open the page', function (string $route, PermissionName $permission) {
    $this->actingAs(userWithPermissions($permission))
        ->get(route($route))
        ->assertOk();
})->with('management pages');

test('a view permission only opens its own page', function () {
    $user = userWithPermissions(PermissionName::RoleView);

    $this->actingAs($user)->get(route('role'))->assertOk();
    $this->actingAs($user)->get(route('permission'))->assertForbidden();
    $this->actingAs($user)->get(route('user'))->assertForbidden();
});

test('super-admins can open every page', function (string $route) {
    $this->actingAs(superAdmin())
        ->get(route($route))
        ->assertOk();
})->with('management pages');

test('sidebar only shows links the user may open', function () {
    $this->actingAs(userWithPermissions(PermissionName::RoleView))
        ->get(route('dashboard'))
        ->assertSee(route('role'))
        ->assertDontSee(route('permission'))
        ->assertDontSee(route('user'));
});

test('sidebar hides the access control group without any view permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertDontSee('Access Control');
});
