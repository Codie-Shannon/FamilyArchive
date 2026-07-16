<?php

namespace App\Domain\Duplicates\Enums;

enum DuplicateMatchMethod: string
{
    case ExactSha256 = 'exact_sha256';
}
