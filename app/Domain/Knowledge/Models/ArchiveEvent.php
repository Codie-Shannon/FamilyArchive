<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Database\Factories\ArchiveEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property string $name
 * @property EventType $type
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property DatePrecision $date_precision
 * @property int|null $date_year
 * @property int|null $estimated_decade
 * @property StructuredDateConfidence $date_confidence
 * @property string|null $date_source_note
 * @property int|null $archive_location_id
 * @property string|null $description
 * @property KnowledgeReviewState $review_state
 * @property string|null $review_reason
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int $metadata_revision
 */
#[Fillable([
    'event_id',
    'name',
    'type',
    'starts_on',
    'ends_on',
    'date_precision',
    'date_year',
    'estimated_decade',
    'date_confidence',
    'date_source_note',
    'archive_location_id',
    'description',
    'review_state',
    'review_reason',
    'created_by',
    'reviewed_by',
    'reviewed_at',
    'metadata_revision',
])]
final class ArchiveEvent extends Model
{
    /** @use HasFactory<ArchiveEventFactory> */
    use HasFactory;

    protected $table = 'archive_events';

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'date_precision' => DatePrecision::class,
            'date_confidence' => StructuredDateConfidence::class,
            'review_state' => KnowledgeReviewState::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ArchiveLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(ArchiveLocation::class, 'archive_location_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<ArchiveEventRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ArchiveEventRevision::class);
    }

    /** @return HasMany<EventProvenance, $this> */
    public function provenanceLinks(): HasMany
    {
        return $this->hasMany(EventProvenance::class);
    }

    /** @return BelongsToMany<SourceCollection, $this> */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            SourceCollection::class,
            'archive_event_provenance_links'
        )->withPivot(['id', 'scan_batch_id', 'note', 'attached_by'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<MediaItem, $this> */
    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaItem::class,
            'archive_event_media'
        )->withPivot(['confidence', 'source_note', 'reviewed_by', 'reviewed_at'])
            ->withTimestamps();
    }

    protected static function newFactory(): ArchiveEventFactory
    {
        return ArchiveEventFactory::new();
    }
}
