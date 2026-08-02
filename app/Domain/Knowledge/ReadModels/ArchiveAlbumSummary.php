<?php

namespace App\Domain\Knowledge\ReadModels;

use App\Domain\Knowledge\Enums\ArchiveAlbumType;

final readonly class ArchiveAlbumSummary
{
    /** @param array<int, int> $mediaItemIds */
    public function __construct(
        public ArchiveAlbumType $type,
        public string $stableId,
        public string $name,
        public ?string $subtitle,
        public ?string $description,
        public int $photoCount,
        public ?int $coverVersionId,
        public array $mediaItemIds,
        public ?string $contextUrl = null,
    ) {}
}
