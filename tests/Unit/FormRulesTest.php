<?php

use App\Livewire\Forms\PermissionForm;
use App\Livewire\Forms\RoleForm;
use App\Livewire\Forms\UserRoleForm;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;

function formFor(string $class): object
{
    return new $class(new class extends Component {}, 'form');
}

test('access names are validated as required, bounded, patterned and unique', function (string $class, string $table) {
    $rules = formFor($class)->rules()['name'];

    expect($rules)->toContain('required', 'string', 'max:255', 'regex:'.PermissionForm::ACCESS_NAME_PATTERN);

    $unique = collect($rules)->first(fn ($rule) => $rule instanceof Unique);
    expect($unique)->not->toBeNull()
        ->and((string) $unique)->toStartWith("unique:{$table},name");
})->with([
    'permission' => [PermissionForm::class, 'permissions'],
    'role' => [RoleForm::class, 'roles'],
]);

test('the name pattern accepts and rejects the expected names', function (string $name, bool $valid) {
    expect((bool) preg_match(PermissionForm::ACCESS_NAME_PATTERN, $name))->toBe($valid);
})->with([
    ['post.edit', true],
    ['super-admin', true],
    ['edit-article_v2.beta', true],
    ['Post.Edit', false],
    ['post edit', false],
    ['post@edit', false],
    ['', false],
]);

test('role permissions must be existing permission ids', function () {
    expect(formFor(RoleForm::class)->rules())
        ->toHaveKey('permissions', ['array'])
        ->toHaveKey('permissions.*', ['integer', 'exists:permissions,id']);
});

test('user roles must be existing role ids', function () {
    expect(formFor(UserRoleForm::class)->rules())
        ->toHaveKey('roles', ['array'])
        ->toHaveKey('roles.*', ['integer', 'exists:roles,id']);
});
