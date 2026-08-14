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

    /**
     * @param  array<int, int>|null  $mediaItemIds
     * @return LengthAwarePaginator<int, ApprovedPhotoGalleryItem>
     */
    public function handle(
        User $user,
        int $perPage = 8,
        ?int $mediaItemId = null,
        ?array $mediaItemIds = null,
        ?string $search = null,
        ?int $excludedCuratedCollectionId = null,
        ?int $createdBy = null,
        bool $hidden = false,
    ): LengthAwarePaginator {
        $query = MediaItem::query()
            ->leftJoin('archive_photo_split_members as gallery_split_members', 'media_items.id', '=', 'gallery_split_members.media_item_id')
            ->leftJoin('archive_photo_split_groups as gallery_split_groups', 'gallery_split_members.archive_photo_split_group_id', '=', 'gallery_split_groups.id')
            ->select([
                'media_items.id', 'media_items.archive_id', 'media_items.title', 'media_items.approved_at',
                'media_items.created_by', 'media_items.metadata_revision', 'media_items.hidden_at',
            ])
            ->with(['fileVersions' => fn ($query) => $query
                ->select(['id', 'media_item_id', 'parent_version_id', 'version_type', 'storage_disk', 'mime_type', 'extension', 'generation_status', 'generation_recipe', 'is_preferred'])
                ->whereIn('version_type', [MediaFileVersionType::Original, MediaFileVersionType::EditedFull, MediaFileVersionType::Thumbnail]),
                'fileVersions.restorationCandidate:id,candidate_version_id,source_version_id,review_state',
            ])
            ->where('media_items.media_type', MediaType::Photo)
            ->where('media_items.review_status', MediaReviewStatus::Approved)
            ->whereNotNull('media_items.approved_at');
        $query->when($hidden, fn ($builder) => $builder->whereNotNull('media_items.hidden_at'))
            ->when(! $hidden, fn ($builder) => $builder->whereNull('media_items.hidden_at'));
        if ($createdBy !== null) {
            $query->where('media_items.created_by', $createdBy);
        }
        if ($mediaItemId !== null) {
            $query->whereKey($mediaItemId);
        }
        if ($mediaItemIds !== null) {
            $query->whereKey($mediaItemIds);
        }
        if ($excludedCuratedCollectionId !== null) {
            $query->whereDoesntHave('curatedCollections', fn ($collections) => $collections
                ->whereKey($excludedCuratedCollectionId));
        }
        if (filled($search)) {
            $term = mb_substr(trim((string) $search), 0, 100);
            $query->where(fn ($builder) => $builder
                ->where('media_items.title', 'like', "%{$term}%")
                ->orWhere('media_items.description', 'like', "%{$term}%")
                ->orWhere('media_items.story', 'like', "%{$term}%")
                ->orWhere('media_items.archive_id', 'like', "%{$term}%"));
        }

        $visibleQuery = $hidden && ! $user->isArchiveAdministrator()
            ? $query->where('created_by', $user->id)
            : $this->access->scopeVisible($query, $user);

        $paginator = $visibleQuery
            ->orderByRaw('COALESCE(gallery_split_groups.gallery_approved_at, media_items.approved_at) DESC')
            ->orderByRaw('COALESCE(gallery_split_groups.gallery_archive_id, media_items.archive_id) ASC')
            ->orderByRaw('COALESCE(gallery_split_members.position, 0) ASC')
            ->orderBy('media_items.archive_id')
            ->paginate(max(1, min($perPage, 100)));

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
                createdBy: $item->created_by,
                metadataRevision: $item->metadata_revision,
                hidden: $item->hidden_at !== null,
            );
        });
    }
}
