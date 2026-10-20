<?php

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = userWithPermissions(PermissionName::UserView, PermissionName::UserAssignRole);
    $this->actingAs($this->manager);
});

it('renders successfully', function () {
    Livewire::test('pages::user.index')
        ->assertStatus(200);
});

it('lists users with their roles', function () {
    $user = User::factory()->create(['name' => 'Jane Editor']);
    $user->assignRole(Role::create(['name' => 'editor']));

    Livewire::test('pages::user.index')
        ->assertSee('Jane Editor')
        ->assertSee('editor');
});

it('searches users by name or email', function () {
    User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);
    User::factory()->create(['name' => 'John Roe', 'email' => 'john@example.test']);

    Livewire::test('pages::user.index')
        ->set('search', 'Jane')
        ->assertSee('Jane Doe')
        ->assertDontSee('John Roe')
        ->set('search', 'john@')
        ->assertSee('John Roe')
        ->assertDontSee('Jane Doe');
});

it('loads the user roles when opening the modal', function () {
    $role = Role::create(['name' => 'editor']);
    $user = User::factory()->create()->assignRole($role);

    Livewire::test('pages::user.index')
        ->call('editRoles', $user->id)
        ->assertSet('form.user.id', $user->id)
        ->assertSet('form.roles', [(string) $role->id]);
});

it('assigns and revokes roles', function () {
    $editor = Role::create(['name' => 'editor']);
    $author = Role::create(['name' => 'author']);
    $user = User::factory()->create()->assignRole($editor);

    Livewire::test('pages::user.index')
        ->call('editRoles', $user->id)
        ->set('form.roles', [(string) $author->id])
        ->call('saveRoles')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->hasRole('author'))->toBeTrue()
        ->and($user->hasRole('editor'))->toBeFalse();
});

it('rejects unknown role ids', function () {
    $user = User::factory()->create();

    Livewire::test('pages::user.index')
        ->call('editRoles', $user->id)
        ->set('form.roles', ['99999'])
        ->call('saveRoles')
        ->assertHasErrors(['form.roles.0' => 'exists']);
});

it('hides the super-admin role from non-super-admins', function () {
    $component = Livewire::test('pages::user.index');

    expect($component->instance()->assignableRoles->pluck('name'))
        ->not->toContain(Role::SUPER_ADMIN)
        ->not->toBeEmpty();
});

it('forbids non-super-admins from granting super-admin', function () {
    $user = User::factory()->create();
    $superAdminRole = Role::findByName(Role::SUPER_ADMIN);

    Livewire::test('pages::user.index')
        ->call('editRoles', $user->id)
        ->set('form.roles', [(string) $superAdminRole->id])
        ->call('saveRoles')
        ->assertForbidden();

    expect($user->fresh()->hasRole(Role::SUPER_ADMIN))->toBeFalse();
});

it('forbids non-super-admins from revoking super-admin', function () {
    $admin = superAdmin();
    superAdmin(); // a second one, so "last super-admin" is not what blocks this

    Livewire::test('pages::user.index')
        ->call('editRoles', $admin->id)
        ->set('form.roles', [])
        ->call('saveRoles')
        ->assertForbidden();

    expect($admin->fresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});

it('keeps super-admin when a non-super-admin edits other roles of a super-admin', function () {
    $admin = superAdmin();
    $editor = Role::create(['name' => 'editor']);
    $superAdminRole = Role::findByName(Role::SUPER_ADMIN);

    Livewire::test('pages::user.index')
        ->call('editRoles', $admin->id)
        ->set('form.roles', [(string) $superAdminRole->id, (string) $editor->id])
        ->call('saveRoles')
        ->assertHasNoErrors();

    expect($admin->fresh()->hasAllRoles([Role::SUPER_ADMIN, 'editor']))->toBeTrue();
});

describe('as super-admin', function () {
    beforeEach(function () {
        $this->admin = superAdmin();
        $this->actingAs($this->admin);
    });

    it('can grant super-admin', function () {
        $user = User::factory()->create();
        $role = Role::findByName(Role::SUPER_ADMIN);

        Livewire::test('pages::user.index')
            ->call('editRoles', $user->id)
            ->set('form.roles', [(string) $role->id])
            ->call('saveRoles')
            ->assertHasNoErrors();

        expect($user->fresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
    });

    it('can revoke super-admin while another super-admin remains', function () {
        $other = superAdmin();

        Livewire::test('pages::user.index')
            ->call('editRoles', $other->id)
            ->set('form.roles', [])
            ->call('saveRoles')
            ->assertHasNoErrors();

        expect($other->fresh()->hasRole(Role::SUPER_ADMIN))->toBeFalse();
    });

    it('cannot revoke super-admin from the last super-admin', function () {
        Livewire::test('pages::user.index')
            ->call('editRoles', $this->admin->id)
            ->set('form.roles', [])
            ->call('saveRoles')
            ->assertHasErrors('form.roles');

        expect($this->admin->fresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
    });
});

describe('authorization', function () {
    it('forbids mounting without user.view', function () {
        $this->actingAs(userWithPermissions());

        Livewire::test('pages::user.index')->assertForbidden();
    });

    it('forbids assigning roles without user.assign-role', function () {
        $this->actingAs(userWithPermissions(PermissionName::UserView));
        $user = User::factory()->create();

        Livewire::test('pages::user.index')
            ->call('editRoles', $user->id)
            ->assertForbidden();
    });

    it('hides the roles button without user.assign-role', function () {
        $this->actingAs(userWithPermissions(PermissionName::UserView));

        Livewire::test('pages::user.index')
            ->assertDontSeeHtml('wire:click="editRoles(');
    });
});

it('searches case-insensitively', function () {
    User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);

    Livewire::test('pages::user.index')
        ->set('search', 'JANE@EXAMPLE')
        ->assertSee('Jane Doe');
});
