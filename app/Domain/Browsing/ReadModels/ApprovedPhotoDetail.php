<?php

namespace App\Domain\Browsing\ReadModels;

final readonly class ApprovedPhotoDetail
{
    /**
     * @param  array<string, string|null>  $metadata
     * @param  list<array{id: int, source_id: string, source_name: string, source_type: string, scan_batch_id: ?string, note: ?string}>  $provenance
     * @param  list<array{id: int, source_id: string, source_name: string}>  $availableSources
     * @param  list<array{id: int, source_collection_id: int, scan_batch_id: string, label: string}>  $availableBatches
     */
    public function __construct(
        public int $mediaItemId,
        public string $archiveId,
        public string $title,
        public array $metadata,
        public array $provenance,
        public array $availableSources,
        public array $availableBatches,
        public string $originalStatus,
        public string $webDisplayStatus,
        public ?int $webDisplayVersionId,
        public string $thumbnailStatus,
        public string $recipeLabel,
    ) {}
}
