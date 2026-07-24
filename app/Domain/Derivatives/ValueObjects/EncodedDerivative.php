<?php

namespace App\Domain\Derivatives\ValueObjects;

final readonly class EncodedDerivative
{
    public function __construct(
        public string $bytes,
        public int $width,
        public int $height,
        public int $quality,
        public int $maxLongSide,
        public int $sourceOrientation,
        public bool $orientationApplied,
        public string $encoder,
    ) {}
}
