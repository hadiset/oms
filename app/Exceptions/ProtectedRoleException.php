<?php

namespace App\Exceptions;

use RuntimeException;

class ProtectedRoleException extends RuntimeException
{
    public static function cannotDelete(string $role): self
    {
        return new self("The [{$role}] role is protected and cannot be deleted.");
    }

    public static function cannotRename(string $role): self
    {
        return new self("The [{$role}] role is protected and cannot be renamed.");
    }

    public static function lastSuperAdmin(): self
    {
        return new self('At least one user must keep the super-admin role.');
    }
}
