<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Models\ArchivePhotoSplitGroup;
use App\Domain\Archive\Models\ArchivePhotoSplitMember;
use App\Domain\Derivatives\Services\ApprovedPhotoViewingSource;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Models\User;
use Illuminate\Support\Collection;

final class ArchivePhotoSplitFamily
{
    public function __construct(
        private ApprovedPhotoViewingSource $sources,
        private PhotoVisibilityManager $visibility,
    ) {}

    public function sourceFor(MediaItem $item): ?MediaItem
    {
        $member = ArchivePhotoSplitMember::query()->with('group.sourceMediaItem')
            ->where('media_item_id', $item->id)->first();
        if ($member?->group?->sourceMediaItem instanceof MediaItem) {
            return $member->group->sourceMediaItem;
        }

        $archiveGroup = ArchivePhotoSplitGroup::query()->with('sourceMediaItem')
            ->where('source_media_item_id', $item->id)->latest('id')->first();
        if ($archiveGroup?->sourceMediaItem instanceof MediaItem) {
            return $archiveGroup->sourceMediaItem;
        }

        $region = PhotoSplitRegion::query()->with('proposal.sourceVersion.mediaItem')
            ->where('output_media_item_id', $item->id)->first();
        $legacySource = $region?->proposal?->sourceVersion?->mediaItem;

        return $legacySource instanceof MediaItem ? $legacySource : null;
    }

    /** @param iterable<int, int> $ids
     * @return Collection<int, MediaItem>
     */
    public function batchSources(iterable $ids, User $actor): Collection
    {
        $ids = collect($ids);
        $items = MediaItem::query()->whereKey($ids)->get()->keyBy('id');
        $seen = [];
        $sources = collect();
        foreach ($ids as $id) {
            $item = $items->get($id);
            if (! $item instanceof MediaItem) {
                continue;
            }
            $source = $this->sourceFor($item) ?? $item;
            if (isset($seen[$source->id]) || ! $this->visibility->canManage($actor, $source)) {
                continue;
            }
            $isApprovedPhoto = $source->review_status === MediaReviewStatus::Approved && $source->approved_at !== null;
            if (! $isApprovedPhoto && $this->memberIds($source) === null) {
                continue;
            }
            $seen[$source->id] = true;
            $sources->push($source);
        }

        return $sources;
    }

    /** @param Collection<int, MediaItem> $sources
     * @return list<int>
     */
    public function editableIds(Collection $sources, User $actor): array
    {
        $ids = [];
        foreach ($sources as $source) {
            $members = $this->forEditor($source, $actor);
            if ($members !== []) {
                foreach ($members as $member) {
                    $ids[] = $member['id'];
                }
            } elseif ($source->review_status === MediaReviewStatus::Approved && $source->approved_at !== null) {
                $ids[] = $source->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<array{id:int, archive_id:string, title:string, thumbnail_version_id:?int, current:bool}> */
    public function forEditor(MediaItem $sourceOrMember, User $actor, ?int $selectedId = null): array
    {
        $ids = $this->memberIds($sourceOrMember);
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
                'current' => $item->id === $selectedId,
            ];
        }

        return count($result) > 1 ? $result : [];
    }

    /** @return list<int>|null */
    private function memberIds(MediaItem $item): ?array
    {
        return $this->archiveSplitIds($item) ?? $this->intakeSplitIds($item);
    }

    /** @return list<int>|null */
    private function archiveSplitIds(MediaItem $current): ?array
    {
        $member = ArchivePhotoSplitMember::query()->where('media_item_id', $current->id)->first();
        $groupId = $member instanceof ArchivePhotoSplitMember
            ? $member->archive_photo_split_group_id
            : ArchivePhotoSplitGroup::query()->where('source_media_item_id', $current->id)->latest('id')->value('id');
        if ($groupId === null) {
            return null;
        }

        $ids = ArchivePhotoSplitMember::query()
            ->where('archive_photo_split_group_id', $groupId)
            ->orderBy('position')
            ->pluck('media_item_id')
            ->all();

        return array_map(static fn ($id): int => (int) $id, array_values($ids));
    }

    /** @return list<int>|null */
    private function intakeSplitIds(MediaItem $current): ?array
    {
        $region = PhotoSplitRegion::query()->where('output_media_item_id', $current->id)->first();
        $proposalId = $region instanceof PhotoSplitRegion
            ? $region->photo_split_proposal_id
            : PhotoSplitProposal::query()
                ->whereHas('sourceVersion', fn ($query) => $query->where('media_item_id', $current->id))
                ->where('state', 'published')
                ->latest('id')
                ->value('id');
        if ($proposalId === null) {
            return null;
        }

        $ids = PhotoSplitRegion::query()
            ->where('photo_split_proposal_id', $proposalId)
            ->whereNotNull('output_media_item_id')
            ->orderBy('position')
            ->pluck('output_media_item_id')
            ->all();

        return array_map(static fn ($id): int => (int) $id, array_values($ids));
    }
}
