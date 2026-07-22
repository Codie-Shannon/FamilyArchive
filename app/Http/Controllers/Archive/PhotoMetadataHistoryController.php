<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Domain\Metadata\Queries\PhotoMetadataHistoryQuery;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PhotoMetadataHistoryController extends Controller
{
    public function index(
        MediaItem $mediaItem,
        PhotoMetadataHistoryQuery $query
    ): View {
        $this->assertEligible($mediaItem);

        return view('archive.metadata-history', [
            'mediaItem' => $mediaItem,
            'revisions' => $query->handle($mediaItem),
        ]);
    }

    public function show(
        MediaItem $mediaItem,
        PhotoMetadataRevision $revision
    ): View {
        $this->assertEligible($mediaItem);

        abort_unless(
            $revision->media_item_id === $mediaItem->id,
            404
        );

        $revision->load('actor:id,name');

        return view('archive.metadata-revision', [
            'mediaItem' => $mediaItem,
            'revision' => $revision,
        ]);
    }

    private function assertEligible(MediaItem $mediaItem): void
    {
        abort_unless(
            $mediaItem->media_type === MediaType::Photo
            && $mediaItem->review_status === MediaReviewStatus::Approved
            && $mediaItem->approved_at !== null,
            404
        );
    }
}
