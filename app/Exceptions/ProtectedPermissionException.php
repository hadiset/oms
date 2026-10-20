<?php

namespace App\Exceptions;

use RuntimeException;

class ProtectedPermissionException extends RuntimeException
{
    public static function cannotDelete(string $permission): self
    {
        return new self("The [{$permission}] permission is used by the application and cannot be deleted.");
    }

    public static function cannotRename(string $permission): self
    {
        return new self("The [{$permission}] permission is used by the application and cannot be renamed.");
    }
}
