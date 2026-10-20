<?php

use App\Enums\PermissionName;
use App\Livewire\Forms\PermissionForm;

test('permission names are unique', function () {
    $values = PermissionName::values();

    expect($values)->toBe(array_values(array_unique($values)));
});

test('permission names pass the name validation pattern', function (PermissionName $permission) {
    expect($permission->value)->toMatch(PermissionForm::ACCESS_NAME_PATTERN);
})->with(PermissionName::cases());

test('values() returns every case value', function () {
    expect(PermissionName::values())->toHaveCount(count(PermissionName::cases()))
        ->toContain('permission.view', 'role.update', 'user.assign-role');
});
