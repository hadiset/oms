<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Exceptions\ProtectedPermissionException;
use App\Models\Concerns\Searchable;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Permission extends SpatiePermission
{
    use Searchable;

    /**
     * Guard the permissions the application checks in code (PermissionName),
     * at the model level because Gate::before lets super-admins skip policies.
     *
     * The deleting guard is registered before parent::boot() so it runs ahead
     * of Spatie's HasRoles listener, which detaches the permission from roles first.
     */
    protected static function boot(): void
    {
        static::deleting(function (Permission $permission) {
            if ($permission->isSystem()) {
                throw ProtectedPermissionException::cannotDelete($permission->name);
            }
        });

        parent::boot();
    }

    protected static function booted(): void
    {
        static::updating(function (Permission $permission) {
            if ($permission->isDirty('name') && $permission->isSystem()) {
                throw ProtectedPermissionException::cannotRename($permission->getOriginal('name'));
            }
        });
    }

    /**
     * Whether this permission is one of the application's own (see PermissionName).
     */
    public function isSystem(): bool
    {
        return PermissionName::tryFrom($this->getOriginal('name', $this->name)) !== null;
    }

    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return ['name'];
    }
}
