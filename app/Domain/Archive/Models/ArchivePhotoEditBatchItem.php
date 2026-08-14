<?php

namespace App\Domain\Archive\Models;

use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, bool|float|int> $settings
 * @property int $expected_metadata_revision
 * @property bool $from_source_scan
 * @property int $position
 * @property int $attempts
 */
#[Fillable([
    'archive_photo_edit_batch_id', 'media_item_id', 'source_version_id', 'draft_id', 'draft_fingerprint', 'draft_client_revision',
    'settings', 'expected_metadata_revision', 'from_source_scan', 'position', 'state', 'attempts',
    'published_version_id', 'failure_code', 'failure_message', 'started_at', 'completed_at',
])]
final class ArchivePhotoEditBatchItem extends Model
{
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'expected_metadata_revision' => 'integer',
            'from_source_scan' => 'boolean',
            'position' => 'integer',
            'attempts' => 'integer',
            'draft_client_revision' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ArchivePhotoEditBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ArchivePhotoEditBatch::class, 'archive_photo_edit_batch_id');
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'source_version_id');
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'published_version_id');
    }
}
