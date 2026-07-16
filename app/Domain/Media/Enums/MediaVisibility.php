<?php

namespace App\Domain\Media\Enums;

enum MediaVisibility: string
{
    case AdminOnly = 'admin_only';
    case PrivateArchive = 'private_archive';
    case FamilyVisible = 'family_visible';
    case BranchVisible = 'branch_visible';
    case HiddenSensitive = 'hidden_sensitive';
    case PublicHighlightCandidate = 'public_highlight_candidate';
    case PublicHighlightApproved = 'public_highlight_approved';
}
