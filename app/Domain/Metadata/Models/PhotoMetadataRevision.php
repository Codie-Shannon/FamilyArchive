<?php

namespace App\Domain\Metadata\Models;

use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PhotoMetadataRevision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'media_metadata_revisions';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createImmutable(array $attributes): self
    {
        return self::query()->create($attributes);
    }

    protected static function booted(): void
    {
        self::updating(
            fn (): never => throw new LogicException(
                'Metadata revisions are immutable.'
            )
        );

        self::deleting(
            fn (): never => throw new LogicException(
                'Metadata revisions are immutable.'
            )
        );
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
