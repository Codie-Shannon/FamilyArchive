<?php

namespace App\Domain\Duplicates\Enums;

enum DuplicateReviewDecision: string
{
    case ConfirmedDuplicate = 'confirmed_duplicate';
    case AlternateSource = 'alternate_source';
    case RelatedButDistinct = 'related_but_distinct';
    case NotDuplicate = 'not_duplicate';

    public function requiresInitialNote(): bool
    {
        return in_array($this, [self::AlternateSource, self::RelatedButDistinct], true);
    }
}
