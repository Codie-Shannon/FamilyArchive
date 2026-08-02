<?php

namespace App\Domain\Knowledge\Services;

use App\Domain\Access\Services\ArchiveAccess;
use App\Domain\Derivatives\Services\ApprovedPhotoViewingSource;
use App\Domain\Knowledge\Enums\ArchiveAlbumType;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\CuratedCollection;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\ReadModels\ArchiveAlbumSummary;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ArchiveAlbumExplorer
{
    public function __construct(
        private readonly ArchiveKnowledgeAccess $knowledgeAccess,
        private readonly ArchiveAccess $archiveAccess,
        private readonly ApprovedPhotoViewingSource $viewingSources,
    ) {}

    /** @return Collection<int, ArchiveAlbumSummary> */
    public function browse(User $user, ?string $query = null): Collection
    {
        $albums = collect()
            ->concat($this->curatedAlbums($user))
            ->concat($this->eventAlbums($user))
            ->concat($this->placeAlbums($user))
            ->concat($this->personAlbums($user))
            ->concat($this->branchAlbums($user));

        $term = mb_strtolower(trim((string) $query));
        if ($term !== '') {
            $albums = $albums->filter(fn (ArchiveAlbumSummary $album): bool => str_contains(
                mb_strtolower(implode(' ', array_filter([$album->name, $album->subtitle, $album->description, $album->type->label()]))),
                $term,
            ));
        }

        return $albums
            ->sortBy(fn (ArchiveAlbumSummary $album): string => sprintf(
                '%d-%s',
                $album->type === ArchiveAlbumType::Curated ? 0 : 1,
                mb_strtolower($album->name),
            ))
            ->values();
    }

    public function find(User $user, ArchiveAlbumType $type, string $stableId): ?ArchiveAlbumSummary
    {
        return $this->browse($user)
            ->first(fn (ArchiveAlbumSummary $album): bool => $album->type === $type && hash_equals($album->stableId, $stableId));
    }

    /** @return Collection<int, ArchiveAlbumSummary> */
    private function curatedAlbums(User $user): Collection
    {
        $query = CuratedCollection::query()->with('mediaItems:id');
        if (! $user->canManageTrustedIntake()) {
            $query->where('is_published', true);
        }

        return $query->orderBy('name')->get()->map(function (CuratedCollection $album) use ($user): ArchiveAlbumSummary {
            $mediaIds = $this->visiblePhotoIds($album->mediaItems->modelKeys(), $user);

            return $this->summary(
                ArchiveAlbumType::Curated,
                $album->collection_id,
                $album->name,
                null,
                $album->description,
                $mediaIds,
            );
        })->filter(fn (ArchiveAlbumSummary $album): bool => $user->canManageTrustedIntake() || $album->photoCount > 0)->values();
    }

    /** @return Collection<int, ArchiveAlbumSummary> */
    private function eventAlbums(User $user): Collection
    {
        return $this->knowledgeAccess->events(ArchiveEvent::query(), $user)
            ->with('location:id,label,subtitle')
            ->orderBy('name')
            ->get()
            ->map(function (ArchiveEvent $event) use ($user): ArchiveAlbumSummary {
                $ids = DB::table('archive_event_media')->where('archive_event_id', $event->id)->pluck('media_item_id')->all();

                return $this->summary(
                    ArchiveAlbumType::Event,
                    $event->event_id,
                    $event->name,
                    $event->location?->label,
                    $event->description,
                    $this->visiblePhotoIds($ids, $user),
                    route('archive.events.show', $event),
                );
            })->filter(fn (ArchiveAlbumSummary $album): bool => $album->photoCount > 0)->values();
    }

    /** @return Collection<int, ArchiveAlbumSummary> */
    private function placeAlbums(User $user): Collection
    {
        return $this->knowledgeAccess->locations(ArchiveLocation::query(), $user)
            ->orderBy('label')
            ->get()
            ->map(function (ArchiveLocation $location) use ($user): ArchiveAlbumSummary {
                $ids = DB::table('archive_event_media')
                    ->join('archive_events', 'archive_events.id', '=', 'archive_event_media.archive_event_id')
                    ->where('archive_events.archive_location_id', $location->id)
                    ->pluck('archive_event_media.media_item_id')->all();

                return $this->summary(
                    ArchiveAlbumType::Place,
                    $location->location_id,
                    $location->label,
                    $location->subtitle,
                    $location->address ?: collect([$location->locality, $location->region, $location->country_code])->filter()->implode(', '),
                    $this->visiblePhotoIds($ids, $user),
                    route('archive.locations.show', $location),
                );
            })->filter(fn (ArchiveAlbumSummary $album): bool => $album->photoCount > 0)->values();
    }

    /** @return Collection<int, ArchiveAlbumSummary> */
    private function personAlbums(User $user): Collection
    {
        return $this->knowledgeAccess->people(ArchivePerson::query(), $user)
            ->orderBy('display_name')
            ->get()
            ->map(function (ArchivePerson $person) use ($user): ArchiveAlbumSummary {
                $ids = DB::table('archive_person_media')->where('archive_person_id', $person->id)->pluck('media_item_id')->all();

                return $this->summary(
                    ArchiveAlbumType::Person,
                    $person->person_id,
                    $person->display_name,
                    $person->birth_year || $person->death_year ? trim(($person->birth_year ?: '?').'–'.($person->death_year ?: '')) : null,
                    null,
                    $this->visiblePhotoIds($ids, $user),
                    route('archive.people.show', $person),
                );
            })->filter(fn (ArchiveAlbumSummary $album): bool => $album->photoCount > 0)->values();
    }

    /** @return Collection<int, ArchiveAlbumSummary> */
    private function branchAlbums(User $user): Collection
    {
        return $this->knowledgeAccess->branches(FamilyBranch::query(), $user)
            ->orderBy('name')
            ->get()
            ->map(function (FamilyBranch $branch) use ($user): ArchiveAlbumSummary {
                $ids = DB::table('archive_person_media')
                    ->join('archive_people', 'archive_people.id', '=', 'archive_person_media.archive_person_id')
                    ->where('archive_people.family_branch_id', $branch->id)
                    ->pluck('archive_person_media.media_item_id')->all();

                return $this->summary(
                    ArchiveAlbumType::Branch,
                    $branch->branch_id,
                    $branch->name,
                    null,
                    $branch->description,
                    $this->visiblePhotoIds($ids, $user),
                    route('archive.branches.show', $branch),
                );
            })->filter(fn (ArchiveAlbumSummary $album): bool => $album->photoCount > 0)->values();
    }

    /**
     * @param  array<int, mixed>  $candidateIds
     * @return array<int, int>
     */
    private function visiblePhotoIds(array $candidateIds, User $user): array
    {
        if ($candidateIds === []) {
            return [];
        }

        return $this->archiveAccess->scopeVisible(
            MediaItem::query()
                ->whereKey($candidateIds)
                ->where('media_type', MediaType::Photo)
                ->where('review_status', MediaReviewStatus::Approved)
                ->whereNotNull('approved_at'),
            $user,
        )->orderByDesc('approved_at')->orderBy('archive_id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param array<int, int> $mediaIds */
    private function summary(
        ArchiveAlbumType $type,
        string $stableId,
        string $name,
        ?string $subtitle,
        ?string $description,
        array $mediaIds,
        ?string $contextUrl = null,
    ): ArchiveAlbumSummary {
        return new ArchiveAlbumSummary(
            type: $type,
            stableId: $stableId,
            name: $name,
            subtitle: filled($subtitle) ? $subtitle : null,
            description: filled($description) ? $description : null,
            photoCount: count($mediaIds),
            coverVersionId: $this->coverVersionId($mediaIds[0] ?? null),
            mediaItemIds: $mediaIds,
            contextUrl: $contextUrl,
        );
    }

    private function coverVersionId(?int $mediaItemId): ?int
    {
        if ($mediaItemId === null) {
            return null;
        }

        $item = MediaItem::query()->with([
            'fileVersions' => fn ($query) => $query->whereIn('version_type', [
                MediaFileVersionType::Original,
                MediaFileVersionType::EditedFull,
                MediaFileVersionType::Thumbnail,
            ]),
            'fileVersions.restorationCandidate:id,candidate_version_id,source_version_id,review_state',
        ])->find($mediaItemId);

        if (! $item instanceof MediaItem) {
            return null;
        }

        $source = $this->viewingSources->resolve($item);
        if (! $source instanceof MediaFileVersion) {
            return null;
        }

        return $item->fileVersions
            ->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Thumbnail
                && $version->generation_status === GenerationStatus::Ready
                && $version->is_preferred
                && $version->storage_disk === 'archive_derivatives'
                && $version->parent_version_id === $source->id)?->id;
    }
}
