<?php

namespace App\Domain\Media\Enums;

enum DateConfidence: string
{
    case Confirmed = 'confirmed';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Exact = 'exact';
    case Estimated = 'estimated';
    case DecadeOnly = 'decade_only';
    case Unknown = 'unknown';
}
