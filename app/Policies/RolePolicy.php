<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::RoleView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::RoleCreate);
    }

    public function update(User $user, Role $role): bool
    {
        return ! $role->isSuperAdmin() && $user->can(PermissionName::RoleUpdate);
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $role->isSuperAdmin() && $user->can(PermissionName::RoleDelete);
    }
}
