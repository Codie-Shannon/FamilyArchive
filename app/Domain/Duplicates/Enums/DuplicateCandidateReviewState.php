<?php

namespace App\Domain\Duplicates\Enums;

enum DuplicateCandidateReviewState: string
{
    case PendingReview = 'pending_review';
    case Resolved = 'resolved';
}
