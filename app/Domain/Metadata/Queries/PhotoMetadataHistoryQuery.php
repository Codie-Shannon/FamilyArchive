<?php

namespace App\Domain\Metadata\Queries;

use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use Illuminate\Database\Eloquent\Collection;

final class PhotoMetadataHistoryQuery
{
    /**
     * @return Collection<int, PhotoMetadataRevision>
     */
    public function handle(MediaItem $mediaItem): Collection
    {
        return $mediaItem->metadataRevisions()
            ->with('actor:id,name')
            ->orderByDesc('revision_number')
            ->get();
    }
}
