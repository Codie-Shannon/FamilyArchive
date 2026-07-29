<?php

namespace App\Domain\Knowledge\Models;

use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'archive_event_id',
    'source_collection_id',
    'scan_batch_id',
    'note',
    'attached_by',
])]
final class EventProvenance extends Model
{
    protected $table = 'archive_event_provenance_links';

    /** @return BelongsTo<ArchiveEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ArchiveEvent::class, 'archive_event_id');
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
