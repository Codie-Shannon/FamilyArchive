<?php

namespace App\Domain\Knowledge\Enums;

enum PersonNameCertainty: string
{
    case Confirmed = 'confirmed';
    case Probable = 'probable';
    case Uncertain = 'uncertain';
    case Unknown = 'unknown';
}
