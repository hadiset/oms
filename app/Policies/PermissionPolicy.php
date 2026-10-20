<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PermissionView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PermissionCreate);
    }

    public function update(User $user, Permission $permission): bool
    {
        return ! $permission->isSystem() && $user->can(PermissionName::PermissionUpdate);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return ! $permission->isSystem() && $user->can(PermissionName::PermissionDelete);
    }
}
