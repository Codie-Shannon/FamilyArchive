<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Models\User;
use Database\Factories\ArchiveLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $location_id
 * @property string $label
 * @property string|null $subtitle
 * @property string|null $address
 * @property string|null $country_code
 * @property string|null $region
 * @property string|null $locality
 * @property LocationPrecision $precision
 * @property bool $is_sensitive
 * @property KnowledgeReviewState $review_state
 * @property StructuredDateConfidence $confidence
 * @property string|null $source_note
 * @property string|null $review_reason
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int $metadata_revision
 */
#[Fillable([
    'location_id',
    'label',
    'subtitle',
    'address',
    'country_code',
    'region',
    'locality',
    'precision',
    'is_sensitive',
    'review_state',
    'confidence',
    'source_note',
    'review_reason',
    'created_by',
    'reviewed_by',
    'reviewed_at',
    'metadata_revision',
])]
final class ArchiveLocation extends Model
{
    /** @use HasFactory<ArchiveLocationFactory> */
    use HasFactory;

    protected $table = 'archive_locations';

    protected function casts(): array
    {
        return [
            'precision' => LocationPrecision::class,
            'is_sensitive' => 'boolean',
            'review_state' => KnowledgeReviewState::class,
            'confidence' => StructuredDateConfidence::class,
            'reviewed_at' => 'immutable_datetime',
        ];
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

    /** @return HasMany<ArchiveEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ArchiveEvent::class);
    }

    /** @return HasMany<ArchiveLocationRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ArchiveLocationRevision::class);
    }

    protected static function newFactory(): ArchiveLocationFactory
    {
        return ArchiveLocationFactory::new();
    }
}
