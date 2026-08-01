<?php

namespace App\Domain\Knowledge\Services;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ArchiveKnowledgeAccess
{
    /**
     * @param  Builder<ArchivePerson>  $query
     * @return Builder<ArchivePerson>
     */
    public function people(Builder $query, User $user): Builder
    {
        $query->where('review_state', KnowledgeReviewState::Accepted)
            ->where('identity_state', 'confirmed');

        if (! $user->isArchiveAdministrator()) {
            $query->where('is_private', false)
                ->where('life_state', '!=', 'living')
                ->where(function (Builder $builder): void {
                    $builder->whereNull('family_branch_id')
                        ->orWhereHas('familyBranch', fn (Builder $branch) => $branch
                            ->where('review_state', KnowledgeReviewState::Accepted)
                            ->where('is_sensitive', false));
                });
        }

        return $query;
    }

    /**
     * @param  Builder<ArchiveLocation>  $query
     * @return Builder<ArchiveLocation>
     */
    public function locations(Builder $query, User $user): Builder
    {
        $query->where('review_state', KnowledgeReviewState::Accepted);

        if (! $user->isArchiveAdministrator()) {
            $query->where('is_sensitive', false)->where('precision', '!=', 'private');
        }

        return $query;
    }

    /**
     * @param  Builder<ArchiveEvent>  $query
     * @return Builder<ArchiveEvent>
     */
    public function events(Builder $query, User $user): Builder
    {
        $query->where('review_state', KnowledgeReviewState::Accepted);

        if (! $user->isArchiveAdministrator()) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('archive_location_id')
                    ->orWhereHas('location', fn (Builder $location) => $location
                        ->where('review_state', KnowledgeReviewState::Accepted)
                        ->where('is_sensitive', false)
                        ->where('precision', '!=', 'private'));
            });
        }

        return $query;
    }

    /**
     * @param  Builder<FamilyBranch>  $query
     * @return Builder<FamilyBranch>
     */
    public function branches(Builder $query, User $user): Builder
    {
        $query->where('review_state', KnowledgeReviewState::Accepted);

        if (! $user->isArchiveAdministrator()) {
            $query->where('is_sensitive', false);
        }

        return $query;
    }

    public function canViewPerson(ArchivePerson $person, User $user): bool
    {
        return $this->people(ArchivePerson::query()->whereKey($person), $user)->exists();
    }

    public function canViewLocation(ArchiveLocation $location, User $user): bool
    {
        return $this->locations(ArchiveLocation::query()->whereKey($location), $user)->exists();
    }

    public function canViewEvent(ArchiveEvent $event, User $user): bool
    {
        return $this->events(ArchiveEvent::query()->whereKey($event), $user)->exists();
    }

    public function canViewBranch(FamilyBranch $branch, User $user): bool
    {
        return $this->branches(FamilyBranch::query()->whereKey($branch), $user)->exists();
    }
}
