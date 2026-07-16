<?php

namespace App\Domain\Intake\Enums;

enum DuplicateStatus: string
{
    case NotChecked = 'not_checked';
    case NoMatch = 'no_match';
    case PossibleDuplicate = 'possible_duplicate';
    case ConfirmedDuplicate = 'confirmed_duplicate';
    case AlternateSource = 'alternate_source';
    case RelatedButDistinct = 'related_but_distinct';
    case NotDuplicate = 'not_duplicate';
}
