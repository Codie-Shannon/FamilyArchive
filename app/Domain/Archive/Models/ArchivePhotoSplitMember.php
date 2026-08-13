<?php

namespace App\Domain\Archive\Models;

use App\Domain\Media\Models\MediaItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['archive_photo_split_group_id', 'media_item_id', 'position', 'bounds'])]
final class ArchivePhotoSplitMember extends Model
{
    protected function casts(): array
    {
        return ['position' => 'integer', 'bounds' => 'array'];
    }

    /** @return BelongsTo<ArchivePhotoSplitGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ArchivePhotoSplitGroup::class, 'archive_photo_split_group_id');
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }
}
