<?php

namespace App\Domain\Intake\Contracts;

use App\Domain\Intake\ValueObjects\WrittenQuarantineObject;

interface NoOverwriteQuarantineWriter
{
    /** @param resource $source */
    public function write(string $relativePath, $source): WrittenQuarantineObject;

    public function removeCreated(WrittenQuarantineObject $object): void;
}
