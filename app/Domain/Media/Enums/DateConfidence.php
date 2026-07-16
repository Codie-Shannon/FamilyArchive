<?php

namespace App\Domain\Media\Enums;

enum DateConfidence: string
{
    case Exact = 'exact';
    case Estimated = 'estimated';
    case DecadeOnly = 'decade_only';
    case Unknown = 'unknown';
}
