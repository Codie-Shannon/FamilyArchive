<?php

namespace App\Domain\Browsing\ReadModels;

final readonly class ApprovedPhotoDetail
{
    /** @param array<string, string|null> $metadata */
    public function __construct(
        public int $mediaItemId,
        public string $archiveId,
        public string $title,
        public array $metadata,
        public string $originalStatus,
        public string $webDisplayStatus,
        public ?int $webDisplayVersionId,
        public string $thumbnailStatus,
        public string $recipeLabel,
    ) {}
}
