<?php

namespace App\Domain\Derivatives\Services;

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;

final class ApprovedPhotoViewingSource
{
    public function resolve(MediaItem $item): ?MediaFileVersion
    {
        $item->loadMissing('fileVersions.restorationCandidate');

        $edited = $item->fileVersions->first(
            fn (MediaFileVersion $version): bool => $this->isApprovedRestoration($version),
        );

        if ($edited instanceof MediaFileVersion) {
            return $edited;
        }

        $split = $item->fileVersions->first(
            fn (MediaFileVersion $version): bool => $this->isApprovedPhotoSplit($version),
        );

        if ($split instanceof MediaFileVersion) {
            return $split;
        }

        return $item->fileVersions->first(
            fn (MediaFileVersion $version): bool => $this->isPreferredOriginal($version),
        );
    }

    public function isApprovedRestoration(MediaFileVersion $version): bool
    {
        $version->loadMissing('restorationCandidate');
        $candidate = $version->restorationCandidate;

        return $version->version_type === MediaFileVersionType::EditedFull
            && $version->generation_status === GenerationStatus::Ready
            && $version->is_preferred
            && $version->storage_disk === 'archive_derivatives'
            && $version->mime_type === 'image/webp'
            && $version->extension === 'webp'
            && $version->parent_version_id !== null
            && $candidate !== null
            && $candidate->review_state === 'approved'
            && $candidate->source_version_id === $version->parent_version_id;
    }

    public function isPreferredOriginal(MediaFileVersion $version): bool
    {
        return $version->version_type === MediaFileVersionType::Original
            && $version->generation_status === GenerationStatus::Ready
            && $version->is_preferred
            && $version->storage_disk === 'archive_originals'
            && $version->parent_version_id === null;
    }

    public function isApprovedPhotoSplit(MediaFileVersion $version): bool
    {
        return $version->version_type === MediaFileVersionType::EditedFull
            && $version->generation_status === GenerationStatus::Ready
            && $version->is_preferred
            && $version->storage_disk === 'archive_derivatives'
            && $version->mime_type === 'image/webp'
            && $version->extension === 'webp'
            && $version->parent_version_id !== null
            && data_get($version->generation_recipe, 'operation') === 'multi_photo_split'
            && is_string(data_get($version->generation_recipe, 'source_sha256'))
            && preg_match('/^[a-f0-9]{64}$/', strtolower((string) data_get($version->generation_recipe, 'source_sha256'))) === 1;
    }
}
