<?php

namespace App\Domain\Intake\ValueObjects;

final readonly class ValidatedPhotoFacts
{
    public function __construct(public string $originalFilename, public string $mimeType, public string $extension, public int $sizeBytes, public int $width, public int $height) {}
}
