<?php

namespace App\Domain\Media\Enums;

enum DatePrecision: string
{
    case Exact = 'exact';
    case YearOnly = 'year_only';
    case DecadeOnly = 'decade_only';
    case Approximate = 'approximate';
    case Unknown = 'unknown';
}
