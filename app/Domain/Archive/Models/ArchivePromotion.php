<?php

namespace App\Domain\Archive\Models;

use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $incoming_upload_id
 * @property int $media_item_id
 * @property int $original_media_file_version_id
 * @property int $actor_id
 * @property string $source_disk
 * @property string $source_path
 * @property string $target_disk
 * @property string $target_path
 * @property int $source_bytes
 * @property int $target_bytes
 * @property string $source_sha256
 * @property string $target_sha256
 * @property Carbon $promoted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'incoming_upload_id',
    'media_item_id',
    'original_media_file_version_id',
    'actor_id',
    'source_disk',
    'source_path',
    'target_disk',
    'target_path',
    'source_bytes',
    'target_bytes',
    'source_sha256',
    'target_sha256',
    'promoted_at',
])]
final class ArchivePromotion extends Model
{
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Archive promotion audit records are immutable.');
        });

        self::deleting(static function (): never {
            throw new LogicException('Archive promotion audit records cannot be deleted.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_bytes' => 'integer',
            'target_bytes' => 'integer',
            'promoted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<IncomingUpload, $this> */
    public function incomingUpload(): BelongsTo
    {
        return $this->belongsTo(IncomingUpload::class);
    }

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /** @return BelongsTo<MediaFileVersion, $this> */
    public function originalVersion(): BelongsTo
    {
        return $this->belongsTo(MediaFileVersion::class, 'original_media_file_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
