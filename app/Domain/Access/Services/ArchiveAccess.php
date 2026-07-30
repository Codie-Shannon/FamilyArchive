<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\OriginalAccessGrant;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ArchiveAccess
{
    /**
     * @param  Builder<MediaItem>  $query
     * @return Builder<MediaItem>
     */
    public function scopeVisible(Builder $query, User $user): Builder
    {
        if ($user->isArchiveAdministrator()) {
            return $query;
        }

        return $query->where(function (Builder $visibility) use ($user): void {
            $visibility->whereIn('visibility', [
                MediaVisibility::FamilyVisible->value,
                MediaVisibility::PublicHighlightApproved->value,
            ]);

            if ($user->family_branch_id !== null) {
                $visibility->orWhere(function (Builder $branch) use ($user): void {
                    $branch
                        ->where('visibility', MediaVisibility::BranchVisible->value)
                        ->where('family_branch_id', $user->family_branch_id);
                });
            }
        });
    }

    public function canView(User $user, MediaItem $item): bool
    {
        return $this->scopeVisible(MediaItem::query()->whereKey($item->getKey()), $user)->exists();
    }

    public function canViewOriginal(User $user, MediaItem $item): bool
    {
        if (! $this->canView($user, $item)) {
            return false;
        }

        if ($user->isArchiveAdministrator()) {
            return true;
        }

        return OriginalAccessGrant::query()
            ->where('user_id', $user->id)
            ->where('media_item_id', $item->id)
            ->where('effective_at', '<=', now())
            ->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }
}
