<?php

namespace App\Domain\Derivatives\Contracts;

use App\Domain\Derivatives\ValueObjects\WrittenDerivativeObject;

interface NoOverwriteDerivativeWriter
{
    public function write(string $relativePath, string $bytes): WrittenDerivativeObject;

    public function removeCreated(WrittenDerivativeObject $object): void;
}
