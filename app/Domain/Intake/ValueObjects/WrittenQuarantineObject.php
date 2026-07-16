<?php

namespace App\Domain\Intake\ValueObjects;

final readonly class WrittenQuarantineObject
{
    public function __construct(
        public string $relativePath,
        public int $bytesWritten,
        public int $storedBytes,
        public string $sha256,
    ) {}
}
