<?php

namespace App\Domain\Browsing\ReadModels;

final readonly class ApprovedPhotoGalleryItem
{
    public function __construct(
        public int $mediaItemId,
        public string $archiveId,
        public string $title,
        public string $thumbnailStatus,
        public ?int $thumbnailVersionId,
        public string $preservationStatus,
    ) {}
}
