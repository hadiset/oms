<?php

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use SweetAlert2\Laravel\Swal;

beforeEach(function () {
    $this->actingAs(userWithPermissions(
        PermissionName::RoleView,
        PermissionName::RoleCreate,
        PermissionName::RoleUpdate,
        PermissionName::RoleDelete,
    ));
});

it('renders successfully', function () {
    Livewire::test('pages::role.index')
        ->assertStatus(200);
});

it('lists roles with their permission count', function () {
    $role = Role::create(['name' => 'editor']);
    $role->givePermissionTo(Permission::create(['name' => 'post.edit']));

    $component = Livewire::test('pages::role.index')
        ->set('search', 'editor')
        ->assertSee('editor');

    expect($component->instance()->roles->first()->permissions_count)->toBe(1);
});

it('filters roles based on search term', function () {
    Role::create(['name' => 'editor']);
    Role::create(['name' => 'author']);

    Livewire::test('pages::role.index')
        ->set('search', 'edit')
        ->assertSee('editor')
        ->assertDontSee('author');
});

it('can create a role with permissions', function () {
    $edit = Permission::create(['name' => 'post.edit']);
    $publish = Permission::create(['name' => 'post.publish']);

    Livewire::test('pages::role.index')
        ->set('form.name', 'editor')
        ->set('form.permissions', [(string) $edit->id, (string) $publish->id])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::findByName('editor');
    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['post.edit', 'post.publish']);
});

it('can create a role without permissions', function () {
    Livewire::test('pages::role.index')
        ->set('form.name', 'viewer')
        ->call('save')
        ->assertHasNoErrors();

    expect(Role::findByName('viewer')->permissions)->toBeEmpty();
});

it('rejects invalid role names', function (string $name, string $rule) {
    Livewire::test('pages::role.index')
        ->set('form.name', $name)
        ->call('save')
        ->assertHasErrors(['form.name' => $rule]);
})->with([
    'empty' => ['', 'required'],
    'uppercase' => ['Editor', 'regex'],
    'spaces' => ['content editor', 'regex'],
    'longer than 255' => [str_repeat('a', 256), 'max'],
]);

it('rejects duplicate role name', function () {
    Role::create(['name' => 'editor']);

    Livewire::test('pages::role.index')
        ->set('form.name', 'editor')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique']);
});

it('rejects unknown permission ids', function () {
    Livewire::test('pages::role.index')
        ->set('form.name', 'editor')
        ->set('form.permissions', ['99999'])
        ->call('save')
        ->assertHasErrors(['form.permissions.0' => 'exists']);
});

it('loads role data and permissions when editing', function () {
    $permission = Permission::create(['name' => 'post.edit']);
    $role = Role::create(['name' => 'editor'])->givePermissionTo($permission);

    Livewire::test('pages::role.index')
        ->call('edit', $role->id)
        ->assertSet('form.role.id', $role->id)
        ->assertSet('form.name', 'editor')
        ->assertSet('form.permissions', [(string) $permission->id]);
});

it('assigns and revokes permissions when updating', function () {
    $edit = Permission::create(['name' => 'post.edit']);
    $publish = Permission::create(['name' => 'post.publish']);
    $role = Role::create(['name' => 'editor'])->givePermissionTo($edit);

    Livewire::test('pages::role.index')
        ->call('edit', $role->id)
        ->set('form.permissions', [(string) $publish->id])
        ->call('update')
        ->assertHasNoErrors();

    $role->refresh();
    expect($role->hasPermissionTo('post.publish'))->toBeTrue()
        ->and($role->hasPermissionTo('post.edit'))->toBeFalse();
});

it('can rename a role', function () {
    $role = Role::create(['name' => 'editor']);

    Livewire::test('pages::role.index')
        ->call('edit', $role->id)
        ->set('form.name', 'content-editor')
        ->call('update')
        ->assertHasNoErrors();

    expect($role->fresh()->name)->toBe('content-editor');
});

it('allows keeping the same name when updating', function () {
    $role = Role::create(['name' => 'editor']);

    Livewire::test('pages::role.index')
        ->call('edit', $role->id)
        ->call('update')
        ->assertHasNoErrors();
});

it('rejects update to a name used by another role', function () {
    Role::create(['name' => 'author']);
    $role = Role::create(['name' => 'editor']);

    Livewire::test('pages::role.index')
        ->call('edit', $role->id)
        ->set('form.name', 'author')
        ->call('update')
        ->assertHasErrors(['form.name' => 'unique']);
});

it('can delete a role', function () {
    $role = Role::create(['name' => 'editor']);

    Livewire::test('pages::role.index')
        ->call('deleteConfirm', $role->id)
        ->call('delete');

    expect(Role::find($role->id))->toBeNull();
});

it('throws exception when editing non-existent role id', function () {
    Livewire::test('pages::role.index')->call('edit', 99999);
})->throws(ModelNotFoundException::class);

describe('super-admin role', function () {
    it('cannot be edited or deleted by a role manager', function () {
        $superAdmin = Role::findByName(Role::SUPER_ADMIN);

        Livewire::test('pages::role.index')
            ->call('edit', $superAdmin->id)
            ->assertForbidden();

        Livewire::test('pages::role.index')
            ->call('deleteConfirm', $superAdmin->id)
            ->assertForbidden();
    });

    it('cannot be deleted even by a super-admin', function () {
        $this->actingAs(superAdmin());
        $role = Role::findByName(Role::SUPER_ADMIN);

        Livewire::test('pages::role.index')
            ->call('deleteConfirm', $role->id)
            ->call('delete')
            ->assertDispatched(Swal::SESSION_KEY, icon: 'error');

        expect(Role::find($role->id))->not->toBeNull();
    });

    it('cannot be renamed even by a super-admin', function () {
        $this->actingAs(superAdmin());
        $role = Role::findByName(Role::SUPER_ADMIN);

        Livewire::test('pages::role.index')
            ->call('edit', $role->id)
            ->set('form.name', 'not-super')
            ->call('update')
            ->assertDispatched(Swal::SESSION_KEY, icon: 'error');

        expect($role->fresh()->name)->toBe(Role::SUPER_ADMIN);
    });

    it('shows no edit or delete buttons for super-admin', function () {
        $this->actingAs(superAdmin());

        Livewire::test('pages::role.index')
            ->set('search', Role::SUPER_ADMIN)
            ->assertSee('All (bypass)')
            ->assertDontSeeHtml('wire:click="edit(')
            ->assertDontSeeHtml('wire:click="deleteConfirm(');
    });
});

describe('authorization', function () {
    it('forbids mounting without role.view', function () {
        $this->actingAs(userWithPermissions());

        Livewire::test('pages::role.index')->assertForbidden();
    });

    it('forbids creating without role.create', function () {
        $this->actingAs(userWithPermissions(PermissionName::RoleView));

        Livewire::test('pages::role.index')
            ->set('form.name', 'editor')
            ->call('save')
            ->assertForbidden();

        expect(Role::where('name', 'editor')->exists())->toBeFalse();
    });

    it('forbids updating without role.update', function () {
        $this->actingAs(userWithPermissions(PermissionName::RoleView));
        $role = Role::create(['name' => 'editor']);

        Livewire::test('pages::role.index')
            ->call('edit', $role->id)
            ->assertForbidden();
    });

    it('forbids deleting without role.delete', function () {
        $this->actingAs(userWithPermissions(PermissionName::RoleView));
        $role = Role::create(['name' => 'editor']);

        Livewire::test('pages::role.index')
            ->call('deleteConfirm', $role->id)
            ->assertForbidden();

        expect($role->fresh())->not->toBeNull();
    });
});

it('searches case-insensitively', function () {
    Role::create(['name' => 'editor']);

    Livewire::test('pages::role.index')
        ->set('search', 'EDITOR')
        ->assertSee('editor');
});
