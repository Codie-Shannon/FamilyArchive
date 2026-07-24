<?php

namespace App\Domain\Provenance\Models;

use App\Models\User;
use Database\Factories\ScanBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scan_batch_id
 * @property int $source_collection_id
 * @property string $label
 * @property Carbon|null $scanned_on
 * @property string|null $notes
 * @property int $created_by
 */
#[Fillable([
    'scan_batch_id',
    'source_collection_id',
    'label',
    'scanned_on',
    'notes',
    'created_by',
])]
final class ScanBatch extends Model
{
    /** @use HasFactory<ScanBatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scanned_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<SourceCollection, $this> */
    public function sourceCollection(): BelongsTo
    {
        return $this->belongsTo(SourceCollection::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<MediaProvenance, $this> */
    public function provenanceLinks(): HasMany
    {
        return $this->hasMany(MediaProvenance::class);
    }

    protected static function newFactory(): ScanBatchFactory
    {
        return ScanBatchFactory::new();
    }
}
