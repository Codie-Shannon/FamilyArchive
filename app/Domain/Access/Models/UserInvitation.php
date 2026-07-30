<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $invitation_id
 * @property string $email
 * @property string $name
 * @property string $role
 * @property int|null $family_branch_id
 * @property string $token_hash
 * @property int $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'invitation_id', 'email', 'name', 'role', 'family_branch_id', 'token_hash',
    'invited_by', 'expires_at', 'accepted_at', 'revoked_at',
])]
final class UserInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
