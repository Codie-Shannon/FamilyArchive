<?php

namespace App\Domain\Media\Enums;

enum SensitivityStatus: string
{
    case NotFlagged = 'not_flagged';
    case ReviewRequired = 'review_required';
    case Sensitive = 'sensitive';
    case Restricted = 'restricted';
}
