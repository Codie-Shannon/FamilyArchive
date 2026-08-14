<?php

namespace App\Domain\Archive\Models;

use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'media_item_id', 'source_version_id', 'settings', 'expected_metadata_revision', 'from_source_scan', 'client_revision'])]
final class ArchivePhotoEditDraft extends Model
{
    /** @return array<string, bool|float|int> */
    public function editorSettings(): array
    {
        $settings = $this->getAttribute('settings');

        return is_array($settings) ? $settings : [];
    }

    protected function casts(): array
    {
        return ['settings' => 'array', 'expected_metadata_revision' => 'integer', 'from_source_scan' => 'boolean', 'client_revision' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class);
    }
}
