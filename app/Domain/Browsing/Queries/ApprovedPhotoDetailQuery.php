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
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;

final class ApprovedPhotoDetailQuery
{
    public function handle(MediaItem $mediaItem): ?ApprovedPhotoDetail
    {
        $item = MediaItem::query()
            ->with([
                'fileVersions',
                'provenanceLinks.sourceCollection',
                'provenanceLinks.scanBatch',
            ])
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
                'date_confidence' => str_replace('_', ' ', $item->structured_date_confidence->value),
                'date_review_state' => $item->date_review_state->value,
                'date_source_note' => $item->date_source_note,
                'date_reason' => $item->date_reason,
                'metadata_revision' => (string) $item->metadata_revision,
                'media_type' => $item->media_type->value,
            ],
            provenance: $this->provenance($item),
            availableSources: $this->availableSources(),
            availableBatches: $this->availableBatches(),
            originalStatus: $original instanceof MediaFileVersion ? 'verified preferred original' : 'unavailable',
            webDisplayStatus: $web instanceof MediaFileVersion ? 'ready' : ($webFailed ? 'failed_derivative' : 'missing_derivative'),
            webDisplayVersionId: $web?->id,
            thumbnailStatus: $thumb instanceof MediaFileVersion ? 'ready' : ($thumbFailed ? 'failed_derivative' : 'missing_derivative'),
            recipeLabel: 'photo-v1',
        );
    }

    /** @return list<array{id: int, source_id: string, source_name: string, source_type: string, scan_batch_id: ?string, note: ?string}> */
    private function provenance(MediaItem $item): array
    {
        $result = [];

        foreach ($item->provenanceLinks as $link) {
            $result[] = [
                'id' => $link->id,
                'source_id' => $link->sourceCollection->source_id,
                'source_name' => $link->sourceCollection->name,
                'source_type' => $link->sourceCollection->type->value,
                'scan_batch_id' => $link->scanBatch?->scan_batch_id,
                'note' => $link->note,
            ];
        }

        return $result;
    }

    /** @return list<array{id: int, source_id: string, source_name: string}> */
    private function availableSources(): array
    {
        $result = [];

        foreach (SourceCollection::query()->orderBy('name')->get() as $source) {
            $result[] = [
                'id' => $source->id,
                'source_id' => $source->source_id,
                'source_name' => $source->name,
            ];
        }

        return $result;
    }

    /** @return list<array{id: int, source_collection_id: int, scan_batch_id: string, label: string}> */
    private function availableBatches(): array
    {
        $result = [];

        foreach (ScanBatch::query()->orderBy('label')->get() as $batch) {
            $result[] = [
                'id' => $batch->id,
                'source_collection_id' => $batch->source_collection_id,
                'scan_batch_id' => $batch->scan_batch_id,
                'label' => $batch->label,
            ];
        }

        return $result;
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
