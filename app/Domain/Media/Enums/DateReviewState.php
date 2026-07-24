<?php

namespace App\Domain\Media\Enums;

enum DateReviewState: string
{
    case Accepted = 'accepted';
    case Suggestion = 'suggestion';
}
