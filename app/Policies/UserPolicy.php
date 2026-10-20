<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UserView);
    }

    public function assignRole(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserAssignRole);
    }
}
