<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\ArchivePhotoSplitMember;
use App\Domain\Derivatives\Services\ApprovedPhotoViewingSource;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Models\User;

final class ArchivePhotoSplitFamily
{
    public function __construct(
        private ApprovedPhotoViewingSource $sources,
        private PhotoVisibilityManager $visibility,
    ) {}

    /** @return list<array{id:int, archive_id:string, title:string, thumbnail_version_id:?int, current:bool}> */
    public function forEditor(MediaItem $current, User $actor): array
    {
        $ids = $this->archiveSplitIds($current) ?? $this->intakeSplitIds($current);
        if ($ids === null || count($ids) < 2) {
            return [];
        }

        $items = MediaItem::query()
            ->with('fileVersions.restorationCandidate')
            ->whereKey($ids)
            ->where('review_status', MediaReviewStatus::Approved)
            ->whereNotNull('approved_at')
            ->get()
            ->keyBy('id');
        $result = [];
        foreach ($ids as $id) {
            $item = $items->get($id);
            if (! $item instanceof MediaItem || ! $this->visibility->canManage($actor, $item)) {
                continue;
            }
            $source = $this->sources->resolve($item);
            $thumbnail = $source instanceof MediaFileVersion
                ? $item->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Thumbnail
                    && $version->generation_status === GenerationStatus::Ready
                    && $version->is_preferred
                    && $version->parent_version_id === $source->id
                    && $version->storage_disk === 'archive_derivatives'
                    && $version->mime_type === 'image/webp')
                : null;
            $result[] = [
                'id' => $item->id,
                'archive_id' => $item->archive_id,
                'title' => filled($item->title) ? (string) $item->title : 'Untitled archive photo',
                'thumbnail_version_id' => $thumbnail?->id,
                'current' => $item->id === $current->id,
            ];
        }

        return count($result) > 1 ? $result : [];
    }

    /** @return list<int>|null */
    private function archiveSplitIds(MediaItem $current): ?array
    {
        $member = ArchivePhotoSplitMember::query()->where('media_item_id', $current->id)->first();
        if (! $member instanceof ArchivePhotoSplitMember) {
            return null;
        }

        $ids = ArchivePhotoSplitMember::query()
            ->where('archive_photo_split_group_id', $member->archive_photo_split_group_id)
            ->orderBy('position')
            ->pluck('media_item_id')
            ->all();

        return array_map(static fn ($id): int => (int) $id, array_values($ids));
    }

    /** @return list<int>|null */
    private function intakeSplitIds(MediaItem $current): ?array
    {
        $region = PhotoSplitRegion::query()->where('output_media_item_id', $current->id)->first();
        if (! $region instanceof PhotoSplitRegion) {
            return null;
        }

        $ids = PhotoSplitRegion::query()
            ->where('photo_split_proposal_id', $region->photo_split_proposal_id)
            ->whereNotNull('output_media_item_id')
            ->orderBy('position')
            ->pluck('output_media_item_id')
            ->all();

        return array_map(static fn ($id): int => (int) $id, array_values($ids));
    }
}
