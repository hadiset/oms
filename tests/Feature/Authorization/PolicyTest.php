<?php

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

describe('PermissionPolicy', function () {
    test('each ability requires its permission', function (string $ability, PermissionName $permission, bool $needsModel) {
        seedPermissions();
        $target = $needsModel ? Permission::create(['name' => 'post.edit']) : Permission::class;

        expect(userWithPermissions($permission)->can($ability, $target))->toBeTrue()
            ->and(User::factory()->create()->can($ability, $target))->toBeFalse();
    })->with([
        'viewAny' => ['viewAny', PermissionName::PermissionView, false],
        'create' => ['create', PermissionName::PermissionCreate, false],
        'update' => ['update', PermissionName::PermissionUpdate, true],
        'delete' => ['delete', PermissionName::PermissionDelete, true],
    ]);
});

describe('RolePolicy', function () {
    test('each ability requires its permission', function (string $ability, PermissionName $permission, bool $needsModel) {
        seedPermissions();
        $target = $needsModel ? Role::create(['name' => 'editor']) : Role::class;

        expect(userWithPermissions($permission)->can($ability, $target))->toBeTrue()
            ->and(User::factory()->create()->can($ability, $target))->toBeFalse();
    })->with([
        'viewAny' => ['viewAny', PermissionName::RoleView, false],
        'create' => ['create', PermissionName::RoleCreate, false],
        'update' => ['update', PermissionName::RoleUpdate, true],
        'delete' => ['delete', PermissionName::RoleDelete, true],
    ]);

    test('the super-admin role cannot be updated or deleted by role managers', function () {
        $user = userWithPermissions(PermissionName::RoleUpdate, PermissionName::RoleDelete);
        $superAdmin = Role::findByName(Role::SUPER_ADMIN);

        expect($user->can('update', $superAdmin))->toBeFalse()
            ->and($user->can('delete', $superAdmin))->toBeFalse();
    });
});

describe('UserPolicy', function () {
    test('viewAny requires user.view', function () {
        seedPermissions();

        expect(userWithPermissions(PermissionName::UserView)->can('viewAny', User::class))->toBeTrue()
            ->and(User::factory()->create()->can('viewAny', User::class))->toBeFalse();
    });

    test('assignRole requires user.assign-role', function () {
        seedPermissions();
        $target = User::factory()->create();

        expect(userWithPermissions(PermissionName::UserAssignRole)->can('assignRole', $target))->toBeTrue()
            ->and(userWithPermissions(PermissionName::UserView)->can('assignRole', $target))->toBeFalse();
    });
});

describe('PermissionPolicy system permissions', function () {
    test('system permissions cannot be updated or deleted by permission managers', function () {
        $user = userWithPermissions(PermissionName::PermissionUpdate, PermissionName::PermissionDelete);
        $system = Permission::findByName(PermissionName::RoleView->value);

        expect($user->can('update', $system))->toBeFalse()
            ->and($user->can('delete', $system))->toBeFalse();
    });
});
