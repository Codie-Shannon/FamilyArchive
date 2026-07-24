<?php

namespace App\Domain\Derivatives\ValueObjects;

use App\Domain\Media\Models\MediaFileVersion;

final readonly class PhotoDerivativeGenerationResult
{
    public function __construct(
        public MediaFileVersion $original,
        public MediaFileVersion $webDisplay,
        public MediaFileVersion $thumbnail,
        public bool $createdWebDisplay,
        public bool $createdThumbnail,
    ) {}
}
