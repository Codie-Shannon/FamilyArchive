<?php

namespace App\Domain\Knowledge\Enums;

enum LocationPrecision: string
{
    case Country = 'country';
    case Region = 'region';
    case Locality = 'locality';
    case Exact = 'exact';
    case Private = 'private';
}
