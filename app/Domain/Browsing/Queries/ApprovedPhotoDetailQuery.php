<?php

namespace App\Domain\Browsing\Queries;

use App\Domain\Browsing\ReadModels\ApprovedPhotoDetail;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;

final class ApprovedPhotoDetailQuery
{
    public function handle(MediaItem $mediaItem): ?ApprovedPhotoDetail
    {
        $item = MediaItem::query()
            ->with('fileVersions')
            ->whereKey($mediaItem->getKey())
            ->where('media_type', MediaType::Photo)
            ->where('review_status', MediaReviewStatus::Approved)
            ->whereNotNull('approved_at')
            ->first();

        if (! $item instanceof MediaItem) {
            return null;
        }

        $original = $item->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Original
            && $version->generation_status === GenerationStatus::Ready
            && $version->is_preferred
        );
        $web = $this->eligibleDerivative($item, $original, MediaFileVersionType::WebDisplay);
        $thumb = $this->eligibleDerivative($item, $original, MediaFileVersionType::Thumbnail);
        $webFailed = $item->fileVersions->contains(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::WebDisplay && $version->generation_status === GenerationStatus::Failed);
        $thumbFailed = $item->fileVersions->contains(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Thumbnail && $version->generation_status === GenerationStatus::Failed);

        return new ApprovedPhotoDetail(
            mediaItemId: $item->id,
            archiveId: $item->archive_id,
            title: filled($item->title) ? (string) $item->title : 'Untitled archive photo',
            metadata: [
                'description' => $item->description,
                'story' => $item->story,
                'date' => match ($item->date_precision) {
                    DatePrecision::Exact => $item->canonical_date?->format('j F Y') ?? 'Invalid exact date',
                    DatePrecision::Approximate => $item->canonical_date?->format('Around j F Y') ?? 'Invalid approximate date',
                    DatePrecision::YearOnly => $item->date_year ? (string) $item->date_year : 'Invalid year-only date',
                    DatePrecision::DecadeOnly => $item->estimated_decade ? $item->estimated_decade.'s' : 'Invalid decade-only date',
                    DatePrecision::Unknown => 'Date not recorded',
                },
                'date_precision' => str_replace('_', ' ', $item->date_precision->value),
                'date_confidence' => str_replace('_', ' ', $item->date_confidence->value),
                'date_review_state' => $item->date_review_state->value,
                'date_source_note' => $item->date_source_note,
                'date_reason' => $item->date_reason,
                'media_type' => $item->media_type->value,
            ],
            originalStatus: $original instanceof MediaFileVersion ? 'verified preferred original' : 'unavailable',
            webDisplayStatus: $web instanceof MediaFileVersion ? 'ready' : ($webFailed ? 'failed_derivative' : 'missing_derivative'),
            webDisplayVersionId: $web?->id,
            thumbnailStatus: $thumb instanceof MediaFileVersion ? 'ready' : ($thumbFailed ? 'failed_derivative' : 'missing_derivative'),
            recipeLabel: 'photo-v1',
        );
    }

    private function eligibleDerivative(MediaItem $item, ?MediaFileVersion $original, MediaFileVersionType $type): ?MediaFileVersion
    {
        if (! $original instanceof MediaFileVersion) {
            return null;
        }

        return $item->fileVersions->first(fn (MediaFileVersion $version): bool => $version->version_type === $type
            && $version->generation_status === GenerationStatus::Ready
            && $version->is_preferred
            && $version->parent_version_id === $original->id
            && $version->storage_disk === 'archive_derivatives'
            && $version->mime_type === 'image/webp'
        );
    }
}
