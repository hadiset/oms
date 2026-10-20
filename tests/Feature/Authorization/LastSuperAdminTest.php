<?php

use App\Exceptions\ProtectedRoleException;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

describe('isLastSuperAdmin', function () {
    test('is true only for the sole super-admin', function () {
        $admin = superAdmin();
        $regular = User::factory()->create();

        expect($admin->isLastSuperAdmin())->toBeTrue()
            ->and($regular->isLastSuperAdmin())->toBeFalse();
    });

    test('is false once a second super-admin exists', function () {
        $admin = superAdmin();
        superAdmin();

        expect($admin->isLastSuperAdmin())->toBeFalse();
    });
});

describe('model guard', function () {
    test('the last super-admin cannot be deleted', function () {
        superAdmin()->delete();
    })->throws(ProtectedRoleException::class);

    test('a super-admin can be deleted while another remains', function () {
        $admin = superAdmin();
        superAdmin();

        $admin->delete();

        expect(User::find($admin->id))->toBeNull();
    });

    test('regular users can be deleted', function () {
        superAdmin();
        $user = User::factory()->create();

        $user->delete();

        expect(User::find($user->id))->toBeNull();
    });
});

describe('account settings', function () {
    test('the last super-admin cannot delete their own account', function () {
        $admin = superAdmin();
        $this->actingAs($admin);

        Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors('account')
            ->assertNoRedirect();

        expect($admin->fresh())->not->toBeNull();
        $this->assertAuthenticatedAs($admin);
    });

    test('a super-admin can delete their account while another remains', function () {
        $admin = superAdmin();
        superAdmin();
        $this->actingAs($admin);

        Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        expect($admin->fresh())->toBeNull();
        $this->assertGuest();
    });

    test('the password is still checked first', function () {
        $this->actingAs(superAdmin());

        Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors('password')
            ->assertHasNoErrors('account');
    });
});

test('a refused deletion leaves the super-admin role attached', function () {
    $admin = superAdmin();

    try {
        $admin->delete();
    } catch (ProtectedRoleException) {
        //
    }

    expect($admin->fresh()->hasRole(Role::SUPER_ADMIN))->toBeTrue();
});
