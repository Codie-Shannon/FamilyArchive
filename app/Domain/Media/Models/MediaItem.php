<?php

namespace App\Domain\Media\Models;

use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\DateReviewState;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Domain\Provenance\Models\MediaProvenance;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Database\Factories\MediaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $archive_id
 * @property MediaType $media_type
 * @property string|null $title
 * @property string|null $description
 * @property string|null $story
 * @property Carbon|null $canonical_date
 * @property DatePrecision $date_precision
 * @property int|null $date_year
 * @property int|null $estimated_decade
 * @property DateConfidence $date_confidence
 * @property StructuredDateConfidence $structured_date_confidence
 * @property DateReviewState $date_review_state
 * @property string|null $date_source_note
 * @property string|null $date_reason
 * @property MediaVisibility $visibility
 * @property MediaReviewStatus $review_status
 * @property SensitivityStatus $sensitivity_status
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property int $metadata_revision
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'archive_id',
    'media_type',
    'title',
    'description',
    'story',
    'canonical_date',
    'date_precision',
    'date_year',
    'estimated_decade',
    'date_confidence',
    'structured_date_confidence',
    'date_review_state',
    'date_source_note',
    'date_reason',
    'visibility',
    'review_status',
    'sensitivity_status',
    'created_by',
    'approved_by',
    'approved_at',
    'metadata_revision',
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
            'date_precision' => DatePrecision::class,
            'date_year' => 'integer',
            'estimated_decade' => 'integer',
            'date_confidence' => DateConfidence::class,
            'structured_date_confidence' => StructuredDateConfidence::class,
            'date_review_state' => DateReviewState::class,
            'visibility' => MediaVisibility::class,
            'review_status' => MediaReviewStatus::class,
            'sensitivity_status' => SensitivityStatus::class,
            'approved_at' => 'immutable_datetime',
            'metadata_revision' => 'integer',
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
     * @return HasMany<PhotoMetadataRevision, $this>
     */
    public function metadataRevisions(): HasMany
    {
        return $this->hasMany(PhotoMetadataRevision::class);
    }

    /** @return HasMany<MediaProvenance, $this> */
    public function provenanceLinks(): HasMany
    {
        return $this->hasMany(MediaProvenance::class);
    }

    /** @return BelongsToMany<SourceCollection, $this> */
    public function sourceCollections(): BelongsToMany
    {
        return $this->belongsToMany(
            SourceCollection::class,
            'media_provenance_links'
        )->withPivot(['id', 'scan_batch_id', 'note', 'attached_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<IncomingUpload, $this>
     */
    public function incomingUploads(): HasMany
    {
        return $this->hasMany(IncomingUpload::class);
    }

    /** @return HasOne<ArchivePromotion, $this> */
    public function archivePromotion(): HasOne
    {
        return $this->hasOne(ArchivePromotion::class);
    }

    protected static function newFactory(): MediaItemFactory
    {
        return MediaItemFactory::new();
    }
}
