<?php

namespace App\Domain\Media\Models;

use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use Database\Factories\MediaFileVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $media_item_id
 * @property int|null $parent_version_id
 * @property MediaFileVersionType $version_type
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $mime_type
 * @property string|null $extension
 * @property int $file_size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property int|null $duration_ms
 * @property string $sha256
 * @property string|null $perceptual_hash
 * @property GenerationStatus $generation_status
 * @property array<string, mixed>|null $generation_recipe
 * @property bool $is_preferred
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'media_item_id',
    'parent_version_id',
    'version_type',
    'storage_disk',
    'storage_path',
    'mime_type',
    'extension',
    'file_size_bytes',
    'width',
    'height',
    'duration_ms',
    'sha256',
    'perceptual_hash',
    'generation_status',
    'generation_recipe',
    'is_preferred',
])]
class MediaFileVersion extends Model
{
    /** @use HasFactory<MediaFileVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_type' => MediaFileVersionType::class,
            'file_size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'generation_status' => GenerationStatus::class,
            'generation_recipe' => 'array',
            'is_preferred' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /**
     * @return BelongsTo<MediaFileVersion, $this>
     */
    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    /**
     * @return HasMany<MediaFileVersion, $this>
     */
    public function derivatives(): HasMany
    {
        return $this->hasMany(self::class, 'parent_version_id');
    }

    /** @return HasMany<DuplicateCandidate, $this> */
    public function matchedByDuplicateCandidates(): HasMany
    {
        return $this->hasMany(DuplicateCandidate::class, 'matched_media_file_version_id');
    }

    /** @return HasOne<ArchivePromotion, $this> */
    public function archivePromotion(): HasOne
    {
        return $this->hasOne(ArchivePromotion::class, 'original_media_file_version_id');
    }

    protected static function newFactory(): MediaFileVersionFactory
    {
        return MediaFileVersionFactory::new();
    }
}
