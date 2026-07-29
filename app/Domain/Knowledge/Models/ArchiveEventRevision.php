<?php

namespace App\Domain\Knowledge\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ArchiveEventRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @param array<string, mixed> $attributes */
    public static function createImmutable(array $attributes): self
    {
        return self::query()->create($attributes);
    }

    protected static function booted(): void
    {
        self::updating(
            fn (): never => throw new LogicException('Event revisions are immutable.')
        );
        self::deleting(
            fn (): never => throw new LogicException('Event revisions are immutable.')
        );
    }

    /** @return BelongsTo<ArchiveEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ArchiveEvent::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
