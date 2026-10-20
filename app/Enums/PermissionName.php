<?php

namespace App\Enums;

/**
 * Permissions used by the application itself.
 *
 * Every case is created by RolePermissionSeeder. Add new cases here when
 * a feature needs its own permission, then re-run the seeder.
 */
enum PermissionName: string
{
    case PermissionView = 'permission.view';
    case PermissionCreate = 'permission.create';
    case PermissionUpdate = 'permission.update';
    case PermissionDelete = 'permission.delete';

    case RoleView = 'role.view';
    case RoleCreate = 'role.create';
    case RoleUpdate = 'role.update';
    case RoleDelete = 'role.delete';

    case UserView = 'user.view';
    case UserAssignRole = 'user.assign-role';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
