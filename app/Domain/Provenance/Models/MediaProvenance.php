<?php

namespace App\Domain\Provenance\Models;

use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $media_item_id
 * @property int $source_collection_id
 * @property int|null $scan_batch_id
 * @property string|null $note
 * @property int $attached_by
 * @property-read SourceCollection $sourceCollection
 * @property-read ScanBatch|null $scanBatch
 */
#[Fillable([
    'media_item_id',
    'source_collection_id',
    'scan_batch_id',
    'note',
    'attached_by',
])]
final class MediaProvenance extends Model
{
    protected $table = 'media_provenance_links';

    /** @return BelongsTo<MediaItem, $this> */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /** @return BelongsTo<SourceCollection, $this> */
    public function sourceCollection(): BelongsTo
    {
        return $this->belongsTo(SourceCollection::class);
    }

    /** @return BelongsTo<ScanBatch, $this> */
    public function scanBatch(): BelongsTo
    {
        return $this->belongsTo(ScanBatch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by');
    }
}
