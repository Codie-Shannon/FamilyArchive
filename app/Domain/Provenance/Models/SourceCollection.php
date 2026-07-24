<?php

namespace App\Domain\Provenance\Models;

use App\Domain\Media\Models\MediaItem;
use App\Domain\Provenance\Enums\SourceCollectionType;
use App\Models\User;
use Database\Factories\SourceCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $source_id
 * @property SourceCollectionType $type
 * @property string $name
 * @property string|null $description
 * @property string|null $physical_reference
 * @property int $created_by
 */
#[Fillable([
    'source_id',
    'type',
    'name',
    'description',
    'physical_reference',
    'created_by',
])]
final class SourceCollection extends Model
{
    /** @use HasFactory<SourceCollectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => SourceCollectionType::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ScanBatch, $this> */
    public function scanBatches(): HasMany
    {
        return $this->hasMany(ScanBatch::class);
    }

    /** @return HasMany<MediaProvenance, $this> */
    public function provenanceLinks(): HasMany
    {
        return $this->hasMany(MediaProvenance::class);
    }

    /** @return BelongsToMany<MediaItem, $this> */
    public function mediaItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaItem::class,
            'media_provenance_links'
        )->withPivot(['id', 'scan_batch_id', 'note', 'attached_by'])
            ->withTimestamps();
    }

    protected static function newFactory(): SourceCollectionFactory
    {
        return SourceCollectionFactory::new();
    }
}
