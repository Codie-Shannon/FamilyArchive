<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'media_item_id', 'granted_by', 'reason', 'effective_at',
    'expires_at', 'revoked_at', 'revocation_reason',
])]
final class OriginalAccessGrant extends Model
{
    protected function casts(): array
    {
        return [
            'effective_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
