<?php

namespace App\Models;

use App\Exceptions\ProtectedRoleException;
use App\Models\Concerns\Searchable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Role extends SpatieRole
{
    use Searchable;

    /**
     * Users with this role pass every Gate check (see AppServiceProvider).
     */
    public const SUPER_ADMIN = 'super-admin';

    /**
     * Guard the super-admin role at the model level, because Gate::before
     * lets super-admins skip policies entirely.
     *
     * The deleting guard is registered before parent::boot() so it runs ahead
     * of Spatie's HasPermissions listener, which detaches permissions first.
     */
    protected static function boot(): void
    {
        static::deleting(function (Role $role) {
            if ($role->isSuperAdmin()) {
                throw ProtectedRoleException::cannotDelete($role->name);
            }
        });

        parent::boot();
    }

    protected static function booted(): void
    {
        static::updating(function (Role $role) {
            if ($role->isDirty('name') && $role->getOriginal('name') === self::SUPER_ADMIN) {
                throw ProtectedRoleException::cannotRename(self::SUPER_ADMIN);
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->getOriginal('name', $this->name) === self::SUPER_ADMIN;
    }

    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return ['name'];
    }
}
