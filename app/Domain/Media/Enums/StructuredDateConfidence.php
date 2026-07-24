<?php

namespace App\Domain\Media\Enums;

enum StructuredDateConfidence: string
{
    case Confirmed = 'confirmed';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Unknown = 'unknown';
}
