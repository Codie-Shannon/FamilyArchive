<?php

namespace App\Domain\Processing\ValueObjects;

final readonly class RenderedSplitPhoto
{
    /**
     * @param  array<string, mixed>  $recipe
     */
    public function __construct(
        public string $bytes,
        public int $width,
        public int $height,
        public array $recipe,
    ) {}
}
