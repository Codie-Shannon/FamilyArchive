<?php

namespace App\Domain\Media\Enums;

enum MediaReviewStatus: string
{
    case PendingReview = 'pending_review';
    case NeedsInfo = 'needs_info';
    case Approved = 'approved';
    case Hidden = 'hidden';
    case Rejected = 'rejected';
}
