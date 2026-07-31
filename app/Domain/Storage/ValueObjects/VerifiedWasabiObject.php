<?php

namespace App\Domain\Storage\ValueObjects;

final readonly class VerifiedWasabiObject
{
    public function __construct(
        public string $relativePath,
        public int $bytes,
        public string $sha256,
        public string $versionId,
    ) {}
}
