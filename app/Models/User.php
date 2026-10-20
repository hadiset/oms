<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Exceptions\ProtectedRoleException;
use App\Models\Concerns\Searchable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, Searchable, TwoFactorAuthenticatable;

    /**
     * Never leave the application without a super-admin, whatever the
     * deletion path (account settings, tinker, a future admin page).
     *
     * Registered before parent::boot() so it runs ahead of Spatie's HasRoles
     * listener, which detaches the user's roles first.
     */
    protected static function boot(): void
    {
        static::deleting(function (User $user) {
            if ($user->isLastSuperAdmin()) {
                throw ProtectedRoleException::lastSuperAdmin();
            }
        });

        parent::boot();
    }

    public function isLastSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN)
            && static::role(Role::SUPER_ADMIN)->count() === 1;
    }

    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'email'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
