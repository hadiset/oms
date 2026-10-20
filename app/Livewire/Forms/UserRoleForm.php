<?php

namespace App\Livewire\Forms;

use App\Exceptions\ProtectedRoleException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class UserRoleForm extends Form
{
    public ?User $user = null;

    /**
     * Role ids (strings, as sent by the checkbox group).
     *
     * @var array<int, int|string>
     */
    public array $roles = [];

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->roles = $user->roles->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * Sync the user's roles (assign checked, revoke unchecked).
     *
     * @throws AuthorizationException when a non-super-admin grants or revokes super-admin
     * @throws ProtectedRoleException when removing super-admin from the last super-admin
     */
    public function save(): void
    {
        $this->validate();

        if ($this->user === null) {
            return;
        }

        $roleIds = array_values(array_map(intval(...), $this->roles));
        $superAdminId = Role::where('name', Role::SUPER_ADMIN)->value('id');

        $hadSuperAdmin = $this->user->hasRole(Role::SUPER_ADMIN);
        $keepsSuperAdmin = $superAdminId !== null && in_array($superAdminId, $roleIds, true);

        if ($hadSuperAdmin !== $keepsSuperAdmin) {
            /** @var User|null $actor */
            $actor = Auth::user();

            if (! $actor?->hasRole(Role::SUPER_ADMIN)) {
                throw new AuthorizationException('Only super-admins can grant or revoke the super-admin role.');
            }

            if ($this->user->isLastSuperAdmin()) {
                throw ProtectedRoleException::lastSuperAdmin();
            }
        }

        $this->user->syncRoles($roleIds);
    }
}
