<?php

namespace App\Domain\Archive\ValueObjects;

final readonly class WrittenOriginalObject
{
    public function __construct(
        public string $relativePath,
        public int $sourceBytes,
        public int $writtenBytes,
        public int $storedBytes,
        public string $sourceSha256,
        public string $writtenSha256,
        public string $storedSha256,
    ) {
    }
}
