<?php

namespace App\Domain\Knowledge\Presenters;

use App\Domain\Knowledge\Models\FamilyBranch;

final class FamilyBranchPresenter
{
    public function browseName(FamilyBranch $branch): string
    {
        return $branch->is_sensitive
            ? 'Restricted family branch'
            : $branch->name;
    }

    public function browseDescription(FamilyBranch $branch): string
    {
        if ($branch->is_sensitive) {
            return 'Branch details are withheld from archive browsing.';
        }

        return $branch->description ?: 'No reviewed branch description.';
    }
}
