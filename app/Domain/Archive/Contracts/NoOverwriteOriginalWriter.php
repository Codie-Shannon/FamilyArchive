<?php

namespace App\Domain\Archive\Contracts;

use App\Domain\Archive\ValueObjects\WrittenOriginalObject;

interface NoOverwriteOriginalWriter
{
    public function copyFromQuarantine(
        string $sourceRelativePath,
        string $targetRelativePath,
        int $expectedBytes,
        string $expectedSha256,
    ): WrittenOriginalObject;

    public function removeCreated(WrittenOriginalObject $object): void;
}
