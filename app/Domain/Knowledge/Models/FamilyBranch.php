<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Database\Factories\FamilyBranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $branch_id
 * @property string $name
 * @property string|null $description
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
    'branch_id',
    'name',
    'description',
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
final class FamilyBranch extends Model
{
    /** @use HasFactory<FamilyBranchFactory> */
    use HasFactory;

    protected $table = 'family_branches';

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'review_state' => KnowledgeReviewState::class,
            'confidence' => StructuredDateConfidence::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ArchivePerson, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(ArchivePerson::class);
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

    /** @return HasMany<FamilyBranchRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(FamilyBranchRevision::class);
    }

    /** @return HasMany<FamilyBranchProvenance, $this> */
    public function provenanceLinks(): HasMany
    {
        return $this->hasMany(FamilyBranchProvenance::class);
    }

    /** @return BelongsToMany<SourceCollection, $this> */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            SourceCollection::class,
            'family_branch_provenance_links'
        )->withPivot(['id', 'scan_batch_id', 'note', 'attached_by'])
            ->withTimestamps();
    }

    protected static function newFactory(): FamilyBranchFactory
    {
        return FamilyBranchFactory::new();
    }
}
