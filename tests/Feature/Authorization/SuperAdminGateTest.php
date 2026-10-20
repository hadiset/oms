<?php

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('super-admins pass every gate check, even undefined abilities', function () {
    $admin = superAdmin();

    expect(Gate::forUser($admin)->allows('anything.at-all'))->toBeTrue();

    foreach (PermissionName::cases() as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }
});

test('regular users only pass checks for permissions they hold', function () {
    $user = userWithPermissions(PermissionName::RoleView);

    expect($user->can(PermissionName::RoleView))->toBeTrue()
        ->and($user->can(PermissionName::RoleCreate))->toBeFalse()
        ->and(Gate::forUser($user)->allows('anything.at-all'))->toBeFalse();
});

test('users without roles pass nothing', function () {
    $user = User::factory()->create();
    seedPermissions();

    foreach (PermissionName::cases() as $permission) {
        expect($user->can($permission))->toBeFalse();
    }
});
