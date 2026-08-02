<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $collection_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_published
 * @property int $curated_by
 */
#[Fillable(['collection_id', 'name', 'description', 'is_published', 'curated_by'])]
final class CuratedCollection extends Model
{
    protected $table = 'curated_collections';

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function curator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'curated_by');
    }

    /** @return BelongsToMany<MediaItem, $this> */
    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(MediaItem::class, 'curated_collection_media')
            ->withPivot(['added_by', 'position'])
            ->withTimestamps()
            ->orderByPivot('position')
            ->orderBy('media_items.archive_id');
    }
}
