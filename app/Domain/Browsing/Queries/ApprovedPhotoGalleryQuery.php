<?php

namespace App\Domain\Browsing\Queries;

use App\Domain\Browsing\ReadModels\ApprovedPhotoGalleryItem;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ApprovedPhotoGalleryQuery
{
    /** @return LengthAwarePaginator<int, ApprovedPhotoGalleryItem> */
    public function handle(int $perPage = 8): LengthAwarePaginator
    {
        $paginator = MediaItem::query()
            ->select(['id', 'archive_id', 'title', 'approved_at'])
            ->with(['fileVersions' => fn ($query) => $query
                ->select(['id', 'media_item_id', 'parent_version_id', 'version_type', 'storage_disk', 'mime_type', 'generation_status', 'is_preferred'])
                ->whereIn('version_type', [MediaFileVersionType::Original, MediaFileVersionType::Thumbnail])])
            ->where('media_type', MediaType::Photo)
            ->where('review_status', MediaReviewStatus::Approved)
            ->whereNotNull('approved_at')
            ->orderByDesc('approved_at')
            ->orderBy('archive_id')
            ->paginate(max(1, min($perPage, 24)));

        return $paginator->through(function (MediaItem $item): ApprovedPhotoGalleryItem {
            $original = $item->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Original
                && $version->generation_status === GenerationStatus::Ready
                && $version->is_preferred
            );
            $thumbnail = $item->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Thumbnail
                && $version->generation_status === GenerationStatus::Ready
                && $version->is_preferred
                && $version->storage_disk === 'archive_derivatives'
                && $version->mime_type === 'image/webp'
                && $original instanceof MediaFileVersion
                && $version->parent_version_id === $original->id
            );
            $failed = $item->fileVersions->contains(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Thumbnail
                && $version->generation_status === GenerationStatus::Failed
            );

            return new ApprovedPhotoGalleryItem(
                mediaItemId: $item->id,
                archiveId: $item->archive_id,
                title: filled($item->title) ? (string) $item->title : 'Untitled archive photo',
                thumbnailStatus: $thumbnail instanceof MediaFileVersion ? 'ready' : ($failed ? 'failed_derivative' : 'missing_derivative'),
                thumbnailVersionId: $thumbnail?->id,
                preservationStatus: $original instanceof MediaFileVersion ? 'verified preferred original' : 'unavailable',
            );
        });
    }
}
