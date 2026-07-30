<?php

namespace App\Domain\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'actor_id', 'event_type', 'previous_values', 'new_values', 'reason', 'created_at'])]
final class AccountAccessEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Access history is append-only.'));
        self::deleting(fn () => throw new \LogicException('Access history is append-only.'));
    }
}
