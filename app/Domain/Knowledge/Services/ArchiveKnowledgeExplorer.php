<?php

namespace App\Domain\Knowledge\Services;

use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\ReadModels\ArchiveKnowledgeSearchResult;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ArchiveKnowledgeExplorer
{
    public function __construct(private readonly ArchiveKnowledgeAccess $access) {}

    /** @return array<string, int> */
    public function counts(User $user): array
    {
        return [
            'events' => $this->access->events(ArchiveEvent::query(), $user)->count(),
            'locations' => $this->access->locations(ArchiveLocation::query(), $user)->count(),
            'people' => $this->access->people(ArchivePerson::query(), $user)->count(),
            'branches' => $this->access->branches(FamilyBranch::query(), $user)->count(),
            'collections' => DB::table('curated_collections')->count(),
        ];
    }

    /** @return Collection<int, ArchiveKnowledgeSearchResult> */
    public function search(string $query, User $user): Collection
    {
        $term = mb_substr(trim($query), 0, 100);

        if ($term === '') {
            return collect();
        }

        $people = $this->access->people(ArchivePerson::query(), $user)
            ->where(fn ($builder) => $builder->where('display_name', 'like', "%{$term}%")->orWhere('person_id', 'like', "%{$term}%"))
            ->limit(25)
            ->get(['person_id', 'display_name'])
            ->map(fn (ArchivePerson $person): ArchiveKnowledgeSearchResult => new ArchiveKnowledgeSearchResult(
                stable_id: $person->person_id,
                label: $person->display_name,
                entity_type: 'person',
            ));

        $events = $this->access->events(ArchiveEvent::query(), $user)
            ->where('name', 'like', "%{$term}%")
            ->limit(25)
            ->get(['event_id', 'name'])
            ->map(fn (ArchiveEvent $event): ArchiveKnowledgeSearchResult => new ArchiveKnowledgeSearchResult(
                stable_id: $event->event_id,
                label: $event->name,
                entity_type: 'event',
            ));

        $locations = $this->access->locations(ArchiveLocation::query(), $user)
            ->where('label', 'like', "%{$term}%")
            ->limit(25)
            ->get(['location_id', 'label'])
            ->map(fn (ArchiveLocation $location): ArchiveKnowledgeSearchResult => new ArchiveKnowledgeSearchResult(
                stable_id: $location->location_id,
                label: $location->label,
                entity_type: 'location',
            ));

        $branches = $this->access->branches(FamilyBranch::query(), $user)
            ->where('name', 'like', "%{$term}%")
            ->limit(25)
            ->get(['branch_id', 'name'])
            ->map(fn (FamilyBranch $branch): ArchiveKnowledgeSearchResult => new ArchiveKnowledgeSearchResult(
                stable_id: $branch->branch_id,
                label: $branch->name,
                entity_type: 'branch',
            ));

        return $people
            ->concat($events)
            ->concat($locations)
            ->concat($branches)
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(50)
            ->values();
    }
}
