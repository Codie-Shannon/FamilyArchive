<?php

namespace App\Domain\Knowledge\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ArchiveKnowledgeExplorer
{
    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'events' => DB::table('archive_events')->where('review_state', 'accepted')->count(),
            'locations' => DB::table('archive_locations')
                ->where('review_state', 'accepted')
                ->where('is_sensitive', false)
                ->where('precision', '!=', 'private')
                ->count(),
            'people' => DB::table('archive_people')
                ->where('identity_state', '!=', 'merged')
                ->where('review_state', 'accepted')
                ->where('life_state', '!=', 'living')
                ->where('is_private', false)
                ->count(),
            'unknown_people' => DB::table('archive_people')
                ->where('identity_state', 'unknown')
                ->where('review_state', 'accepted')
                ->where('life_state', '!=', 'living')
                ->where('is_private', false)
                ->count(),
            'branches' => DB::table('family_branches')
                ->where('review_state', 'accepted')
                ->where('is_sensitive', false)
                ->count(),
            'collections' => DB::table('curated_collections')->count(),
        ];
    }

    /** @return Collection<int, \stdClass> */
    public function search(string $query): Collection
    {
        $term = mb_substr(trim($query), 0, 100);

        if ($term === '') {
            return collect();
        }

        return DB::table('archive_people')
            ->select(['person_id as stable_id', 'display_name as label'])
            ->selectRaw("'person' as entity_type")
            ->where('identity_state', '!=', 'merged')
            ->where('review_state', 'accepted')
            ->where('life_state', '!=', 'living')
            ->where('is_private', false)
            ->where(function ($builder) use ($term): void {
                $builder->where('display_name', 'like', "%{$term}%")
                    ->orWhere('person_id', 'like', "%{$term}%");
            })
            ->unionAll(
                DB::table('archive_events')
                    ->select(['event_id as stable_id', 'name as label'])
                    ->selectRaw("'event' as entity_type")
                    ->where('review_state', 'accepted')
                    ->where('name', 'like', "%{$term}%")
            )
            ->unionAll(
                DB::table('archive_locations')
                    ->select(['location_id as stable_id', 'label'])
                    ->selectRaw("'location' as entity_type")
                    ->where('label', 'like', "%{$term}%")
                    ->where('review_state', 'accepted')
                    ->where('is_sensitive', false)
                    ->where('precision', '!=', 'private')
            )
            ->orderBy('label')
            ->limit(50)
            ->get();
    }
}
