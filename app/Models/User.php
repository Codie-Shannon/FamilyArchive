<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $username
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'role', 'account_state', 'family_branch_id', 'family_connection'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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

    public function isArchiveAdministrator(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function canContribute(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'contributor', 'trusted_contributor'], true);
    }

    public function canManageTrustedIntake(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'trusted_contributor'], true);
    }

    public function canManageFamilyOperations(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function canAccessWorkHub(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'trusted_contributor'], true);
    }

    public function isApprovedFamilyMember(): bool
    {
        return $this->account_state === 'approved'
            && in_array($this->role, ['owner', 'admin', 'trusted_contributor', 'contributor', 'viewer'], true);
    }
}
