<?php

namespace App\Domain\Knowledge\Enums;

enum KnowledgeReviewState: string
{
    case Accepted = 'accepted';
    case Suggestion = 'suggestion';
}
