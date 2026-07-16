<?php

namespace App\Domain\Intake\Enums;

enum IncomingReviewStatus: string
{
    case PendingReview = 'pending_review';
    case PossibleDuplicate = 'possible_duplicate';
    case NeedsInfo = 'needs_info';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
