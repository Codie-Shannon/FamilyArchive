<?php

namespace App\Domain\Intake\ValueObjects;

use InvalidArgumentException;

final readonly class SanitizedUploadFilename
{
    public string $value;

    public function __construct(string $original, string $extension)
    {
        if (preg_match('~[\x00-\x1F\x7F\\/:]~', $original) || str_contains($original, '..')) {
            throw new InvalidArgumentException('The filename contains unsafe path characters.');
        }
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $base));
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'photo';
        }
        $this->value = substr($base, 0, 120).'.'.$extension;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
