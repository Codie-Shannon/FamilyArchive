<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Database\Factories\ArchivePersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $person_id
 * @property string $display_name
 * @property array<int, string>|null $alternate_names
 * @property PersonNameCertainty $name_certainty
 * @property Carbon|null $birth_on
 * @property int|null $birth_year
 * @property int|null $birth_decade
 * @property PersonDatePrecision $birth_precision
 * @property Carbon|null $death_on
 * @property int|null $death_year
 * @property int|null $death_decade
 * @property PersonDatePrecision $death_precision
 * @property string $life_state
 * @property string $identity_state
 * @property StructuredDateConfidence $fact_confidence
 * @property string|null $source_note
 * @property bool $is_private
 * @property int|null $family_branch_id
 * @property string|null $notes
 * @property KnowledgeReviewState $review_state
 * @property string|null $review_reason
 * @property int|null $created_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int $metadata_revision
 */
#[Fillable([
    'person_id',
    'display_name',
    'alternate_names',
    'name_certainty',
    'birth_on',
    'birth_year',
    'birth_decade',
    'birth_precision',
    'death_on',
    'death_year',
    'death_decade',
    'death_precision',
    'life_state',
    'identity_state',
    'fact_confidence',
    'source_note',
    'is_private',
    'family_branch_id',
    'notes',
    'review_state',
    'review_reason',
    'created_by',
    'reviewed_by',
    'reviewed_at',
    'metadata_revision',
])]
final class ArchivePerson extends Model
{
    /** @use HasFactory<ArchivePersonFactory> */
    use HasFactory;

    protected $table = 'archive_people';

    protected function casts(): array
    {
        return [
            'alternate_names' => 'array',
            'name_certainty' => PersonNameCertainty::class,
            'birth_on' => 'immutable_date',
            'birth_precision' => PersonDatePrecision::class,
            'death_on' => 'immutable_date',
            'death_precision' => PersonDatePrecision::class,
            'fact_confidence' => StructuredDateConfidence::class,
            'is_private' => 'boolean',
            'review_state' => KnowledgeReviewState::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<FamilyBranch, $this> */
    public function familyBranch(): BelongsTo
    {
        return $this->belongsTo(FamilyBranch::class);
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

    /** @return HasMany<ArchivePersonRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ArchivePersonRevision::class);
    }

    /** @return HasMany<ArchivePersonProvenance, $this> */
    public function provenanceLinks(): HasMany
    {
        return $this->hasMany(ArchivePersonProvenance::class);
    }

    /** @return BelongsToMany<SourceCollection, $this> */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            SourceCollection::class,
            'archive_person_provenance_links'
        )->withPivot(['id', 'scan_batch_id', 'note', 'attached_by'])
            ->withTimestamps();
    }

    protected static function newFactory(): ArchivePersonFactory
    {
        return ArchivePersonFactory::new();
    }
}
