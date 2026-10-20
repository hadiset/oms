<?php

use App\Enums\PermissionName;
use App\Exceptions\ProtectedPermissionException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\UniqueConstraintViolationException;
use Spatie\Permission\PermissionRegistrar;

beforeEach(fn () => seedPermissions());

test('every application permission is a system permission', function (PermissionName $name) {
    expect(Permission::findByName($name->value)->isSystem())->toBeTrue();
})->with(PermissionName::cases());

test('custom permissions are not system permissions', function () {
    expect(Permission::create(['name' => 'post.edit'])->isSystem())->toBeFalse();
});

test('system permissions cannot be deleted', function (PermissionName $name) {
    Permission::findByName($name->value)->delete();
})->with(PermissionName::cases())->throws(ProtectedPermissionException::class);

test('system permissions cannot be renamed', function () {
    Permission::findByName(PermissionName::RoleView->value)->update(['name' => 'role.list']);
})->throws(ProtectedPermissionException::class);

test('isSystem checks the stored name, not a pending change', function () {
    $permission = Permission::findByName(PermissionName::RoleView->value);
    $permission->name = 'role.list';

    expect($permission->isSystem())->toBeTrue();
});

test('a custom permission cannot be renamed to take over a system name', function () {
    Permission::create(['name' => 'post.edit'])->update(['name' => PermissionName::RoleView->value]);
})->throws(UniqueConstraintViolationException::class);

test('custom permissions can be renamed and deleted', function () {
    $permission = Permission::create(['name' => 'post.edit']);

    $permission->update(['name' => 'post.update']);
    expect($permission->fresh()->name)->toBe('post.update');

    $permission->delete();
    expect(Permission::find($permission->id))->toBeNull();
});

test('spatie resolves permissions through the app Permission model', function () {
    expect(app(PermissionRegistrar::class)->getPermissionClass())->toBe(Permission::class);
});

test('a refused deletion leaves the permission attached to its roles', function () {
    $role = Role::create(['name' => 'manager'])->givePermissionTo(PermissionName::RoleView->value);

    try {
        Permission::findByName(PermissionName::RoleView->value)->delete();
    } catch (ProtectedPermissionException) {
        //
    }

    expect($role->fresh()->hasPermissionTo(PermissionName::RoleView->value))->toBeTrue();
});
