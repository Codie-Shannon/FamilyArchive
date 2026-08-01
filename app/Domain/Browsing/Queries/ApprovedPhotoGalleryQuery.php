<?php

namespace App\Domain\Browsing\Queries;

use App\Domain\Access\Services\ArchiveAccess;
use App\Domain\Browsing\ReadModels\ApprovedPhotoGalleryItem;
use App\Domain\Derivatives\Services\ApprovedPhotoViewingSource;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ApprovedPhotoGalleryQuery
{
    public function __construct(
        private ArchiveAccess $access,
        private ApprovedPhotoViewingSource $sources,
    ) {}

    /** @return LengthAwarePaginator<int, ApprovedPhotoGalleryItem> */
    public function handle(User $user, int $perPage = 8, ?int $mediaItemId = null): LengthAwarePaginator
    {
        $query = MediaItem::query()
            ->select(['id', 'archive_id', 'title', 'approved_at'])
            ->with(['fileVersions' => fn ($query) => $query
                ->select(['id', 'media_item_id', 'parent_version_id', 'version_type', 'storage_disk', 'mime_type', 'extension', 'generation_status', 'is_preferred'])
                ->whereIn('version_type', [MediaFileVersionType::Original, MediaFileVersionType::EditedFull, MediaFileVersionType::Thumbnail]),
                'fileVersions.restorationCandidate:id,candidate_version_id,source_version_id,review_state',
            ])
            ->where('media_type', MediaType::Photo)
            ->where('review_status', MediaReviewStatus::Approved)
            ->whereNotNull('approved_at');
        if ($mediaItemId !== null) {
            $query->whereKey($mediaItemId);
        }

        $paginator = $this->access->scopeVisible($query, $user)
            ->orderByDesc('approved_at')
            ->orderBy('archive_id')
            ->paginate(max(1, min($perPage, 24)));

        return $paginator->through(function (MediaItem $item): ApprovedPhotoGalleryItem {
            $original = $item->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Original
                && $version->generation_status === GenerationStatus::Ready
                && $version->is_preferred
            );
            $source = $this->sources->resolve($item);
            $thumbnail = $item->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Thumbnail
                && $version->generation_status === GenerationStatus::Ready
                && $version->is_preferred
                && $version->storage_disk === 'archive_derivatives'
                && $version->mime_type === 'image/webp'
                && $source instanceof MediaFileVersion
                && $version->parent_version_id === $source->id
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
