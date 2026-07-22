<?php

namespace App\Domain\Derivatives\ValueObjects;

final readonly class WrittenDerivativeObject
{
    public function __construct(
        public string $relativePath,
        public int $bytes,
        public string $sha256,
    ) {
    }
}
