<?php

namespace App\Domain\Archive\Models;

use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source_media_item_id', 'source_version_id', 'created_by', 'source_basis', 'gallery_approved_at', 'gallery_archive_id', 'published_at'])]
final class ArchivePhotoSplitGroup extends Model
{
    protected function casts(): array
    {
        return [
            'gallery_approved_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function sourceMediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'source_media_item_id');
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'source_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ArchivePhotoSplitMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ArchivePhotoSplitMember::class)->orderBy('position');
    }
}
