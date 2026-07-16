<?php

namespace App\Domain\Media\Models;

use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Models\User;
use Database\Factories\MediaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $archive_id
 * @property MediaType $media_type
 * @property string|null $title
 * @property string|null $description
 * @property string|null $story
 * @property Carbon|null $canonical_date
 * @property int|null $estimated_decade
 * @property DateConfidence $date_confidence
 * @property MediaVisibility $visibility
 * @property MediaReviewStatus $review_status
 * @property SensitivityStatus $sensitivity_status
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'archive_id',
    'media_type',
    'title',
    'description',
    'story',
    'canonical_date',
    'estimated_decade',
    'date_confidence',
    'visibility',
    'review_status',
    'sensitivity_status',
    'created_by',
    'approved_by',
    'approved_at',
])]
class MediaItem extends Model
{
    /** @use HasFactory<MediaItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'canonical_date' => 'immutable_date',
            'estimated_decade' => 'integer',
            'date_confidence' => DateConfidence::class,
            'visibility' => MediaVisibility::class,
            'review_status' => MediaReviewStatus::class,
            'sensitivity_status' => SensitivityStatus::class,
            'approved_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<MediaFileVersion, $this>
     */
    public function fileVersions(): HasMany
    {
        return $this->hasMany(MediaFileVersion::class);
    }

    /**
     * @return HasMany<IncomingUpload, $this>
     */
    public function incomingUploads(): HasMany
    {
        return $this->hasMany(IncomingUpload::class);
    }

    protected static function newFactory(): MediaItemFactory
    {
        return MediaItemFactory::new();
    }
}
