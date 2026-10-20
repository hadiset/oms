<?php

use App\Enums\PermissionName;
use App\Models\Permission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use SweetAlert2\Laravel\Swal;

beforeEach(function () {
    $this->actingAs(userWithPermissions(
        PermissionName::PermissionView,
        PermissionName::PermissionCreate,
        PermissionName::PermissionUpdate,
        PermissionName::PermissionDelete,
    ));
});

it('renders successfully', function () {
    Livewire::test('pages::permission.index')
        ->assertStatus(200);
});

it('lists permissions', function () {
    Permission::create(['name' => 'edit.user']);
    Permission::create(['name' => 'add.user']);

    // The app's own seeded permissions fill page 1, so narrow to ours.
    Livewire::test('pages::permission.index')
        ->set('search', '.user')
        ->assertSee('edit.user')
        ->assertSee('add.user');
});

it('filter permission based on search term', function () {
    Permission::create(['name' => 'edit.user']);
    Permission::create(['name' => 'add.user']);

    Livewire::test('pages::permission.index')
        ->set('search', 'edit')
        ->assertSee('edit.user')
        ->assertDontSee('add.user');
});

it('show empty result when search matches nothing', function () {
    Permission::create(['name' => 'edit.user']);
    Permission::create(['name' => 'add.user']);

    Livewire::test('pages::permission.index')
        ->set('search', 'delete')
        ->assertDontSee('edit.user')
        ->assertDontSee('add.user');
});

it('resets page to 1 when search changes', function () {
    foreach (['view', 'edit', 'delete', 'add', 'manage'] as $action) {
        Permission::create(['name' => "{$action}.user"]);
        Permission::create(['name' => "{$action}.permission"]);
    }

    Livewire::test('pages::permission.index')
        ->call('gotoPage', 2)
        ->set('search', 'view')
        ->assertSet('paginators.page', 1);
});

it('search term persists in URL query string', function () {
    Permission::create(['name' => 'edit.user']);

    Livewire::withQueryParams(['q' => 'edit'])
        ->test('pages::permission.index')
        ->assertSet('search', 'edit');
});

it('can create a permission with valid name', function () {
    Livewire::test('pages::permission.index')
        ->set('form.name', 'view.dashboard')
        ->call('save')
        ->assertHasNoErrors();

    expect(Permission::where('name', 'view.dashboard')->exists())->toBeTrue();
});

it('rejects invalid names', function (string $name, string $rule) {
    Livewire::test('pages::permission.index')
        ->set('form.name', $name)
        ->call('save')
        ->assertHasErrors(['form.name' => $rule]);
})->with([
    'empty' => ['', 'required'],
    'uppercase' => ['Edit.Article', 'regex'],
    'spaces' => ['edit article', 'regex'],
    'special characters' => ['edit@article!', 'regex'],
    'only whitespace' => ['   ', 'required'],
    'longer than 255' => [str_repeat('a', 256), 'max'],
]);

it('accepts name with dots, underscores, and dashes', function () {
    Livewire::test('pages::permission.index')
        ->set('form.name', 'edit-article_v2.beta')
        ->call('save')
        ->assertHasNoErrors();
});

it('rejects duplicate permission name', function () {
    Permission::create(['name' => 'edit.article']);

    Livewire::test('pages::permission.index')
        ->set('form.name', 'edit.article')
        ->call('save')
        ->assertHasErrors(['form.name' => 'unique']);
});

it('closes modal and resets form after successful save', function () {
    Livewire::test('pages::permission.index')
        ->set('form.name', 'new.permission')
        ->call('save')
        ->assertSet('form.name', '')
        ->assertSet('form.permission', null);
});

it('loads permission data when editing', function () {
    $permission = Permission::create(['name' => 'old.permission']);

    Livewire::test('pages::permission.index')
        ->call('edit', $permission->id)
        ->assertSet('form.permission.id', $permission->id)
        ->assertSet('form.name', 'old.permission');
});

it('can update a permission', function () {
    $permission = Permission::create(['name' => 'old.permission']);

    Livewire::test('pages::permission.index')
        ->call('edit', $permission->id)
        ->set('form.name', 'new.permission')
        ->call('update')
        ->assertHasNoErrors();

    expect($permission->fresh()->name)->toBe('new.permission');
});

it('throws exception when editing non-existent permission id', function () {
    Livewire::test('pages::permission.index')
        ->call('edit', 99999);
})->throws(ModelNotFoundException::class);

it('allows updating permission to the same name (unchanged)', function () {
    $permission = Permission::create(['name' => 'same.name']);

    Livewire::test('pages::permission.index')
        ->call('edit', $permission->id)
        ->call('update')
        ->assertHasNoErrors();
});

it('rejects update to a name already used by another permission', function () {
    Permission::create(['name' => 'taken.name']);
    $permission = Permission::create(['name' => 'my.name']);

    Livewire::test('pages::permission.index')
        ->call('edit', $permission->id)
        ->set('form.name', 'taken.name')
        ->call('update')
        ->assertHasErrors(['form.name' => 'unique']);
});

it('loads permission data when confirming delete', function () {
    $permission = Permission::create(['name' => 'to.delete']);

    Livewire::test('pages::permission.index')
        ->call('deleteConfirm', $permission->id)
        ->assertSet('form.permission.id', $permission->id)
        ->assertSet('form.name', 'to.delete');
});

it('can delete a permission', function () {
    $permission = Permission::create(['name' => 'to.delete']);

    Livewire::test('pages::permission.index')
        ->call('deleteConfirm', $permission->id)
        ->call('delete');

    expect(Permission::find($permission->id))->toBeNull();
});

it('throws exception when deleting without a loaded permission', function () {
    Livewire::test('pages::permission.index')->call('delete');
})->throws(ModelNotFoundException::class);

it('resets form properties', function () {
    Livewire::test('pages::permission.index')
        ->set('form.name', 'something')
        ->call('resetProp')
        ->assertSet('form.name', '')
        ->assertSet('form.permission', null);
});

describe('authorization', function () {
    it('forbids mounting without permission.view', function () {
        $this->actingAs(userWithPermissions());

        Livewire::test('pages::permission.index')->assertForbidden();
    });

    it('forbids creating without permission.create', function () {
        $this->actingAs(userWithPermissions(PermissionName::PermissionView));

        Livewire::test('pages::permission.index')
            ->set('form.name', 'new.permission')
            ->call('save')
            ->assertForbidden();

        expect(Permission::where('name', 'new.permission')->exists())->toBeFalse();
    });

    it('forbids editing without permission.update', function () {
        $this->actingAs(userWithPermissions(PermissionName::PermissionView));
        $permission = Permission::create(['name' => 'old.permission']);

        Livewire::test('pages::permission.index')
            ->call('edit', $permission->id)
            ->assertForbidden();
    });

    it('forbids deleting without permission.delete', function () {
        $this->actingAs(userWithPermissions(PermissionName::PermissionView));
        $permission = Permission::create(['name' => 'to.delete']);

        Livewire::test('pages::permission.index')
            ->call('deleteConfirm', $permission->id)
            ->assertForbidden();

        expect($permission->fresh())->not->toBeNull();
    });

    it('hides action buttons without the matching permissions', function () {
        $this->actingAs(userWithPermissions(PermissionName::PermissionView));
        Permission::create(['name' => 'some.permission']);

        Livewire::test('pages::permission.index')
            ->set('search', 'some.')
            ->assertSee('some.permission')
            ->assertDontSee('Add Permission')
            ->assertDontSeeHtml('wire:click="edit(')
            ->assertDontSeeHtml('wire:click="deleteConfirm(');
    });
});

describe('system permissions', function () {
    it('marks system permissions and hides their edit and delete buttons', function () {
        $this->actingAs(superAdmin());

        Livewire::test('pages::permission.index')
            ->set('search', PermissionName::RoleView->value)
            ->assertSee('system')
            ->assertDontSeeHtml('wire:click="edit(')
            ->assertDontSeeHtml('wire:click="deleteConfirm(');
    });

    it('cannot be edited or deleted by a permission manager', function () {
        $permission = Permission::findByName(PermissionName::RoleView->value);

        Livewire::test('pages::permission.index')
            ->call('edit', $permission->id)
            ->assertForbidden();

        Livewire::test('pages::permission.index')
            ->call('deleteConfirm', $permission->id)
            ->assertForbidden();
    });

    it('cannot be deleted even by a super-admin', function () {
        $this->actingAs(superAdmin());
        $permission = Permission::findByName(PermissionName::RoleView->value);

        Livewire::test('pages::permission.index')
            ->call('deleteConfirm', $permission->id)
            ->call('delete')
            ->assertDispatched(Swal::SESSION_KEY, icon: 'error');

        expect($permission->fresh())->not->toBeNull();
    });

    it('cannot be renamed even by a super-admin', function () {
        $this->actingAs(superAdmin());
        $permission = Permission::findByName(PermissionName::RoleView->value);

        Livewire::test('pages::permission.index')
            ->call('edit', $permission->id)
            ->set('form.name', 'role.list')
            ->call('update')
            ->assertDispatched(Swal::SESSION_KEY, icon: 'error');

        expect($permission->fresh()->name)->toBe(PermissionName::RoleView->value);
    });
});

it('searches case-insensitively', function () {
    Permission::create(['name' => 'report.export']);

    Livewire::test('pages::permission.index')
        ->set('search', 'REPORT')
        ->assertSee('report.export');
});
