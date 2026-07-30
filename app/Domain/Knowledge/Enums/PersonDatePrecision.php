<?php

namespace App\Domain\Knowledge\Enums;

enum PersonDatePrecision: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
    case YearOnly = 'year_only';
    case DecadeOnly = 'decade_only';
    case Unknown = 'unknown';
}
