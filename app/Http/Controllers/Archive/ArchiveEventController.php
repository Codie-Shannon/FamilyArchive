<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Access\Services\ArchiveAccess;
use App\Domain\Knowledge\Actions\ReviewArchiveEvent;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Presenters\ArchiveEventDatePresenter;
use App\Domain\Knowledge\Presenters\ArchiveLocationPresenter;
use App\Domain\Knowledge\Services\ArchiveKnowledgeAccess;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\SaveArchiveEventRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ArchiveEventController extends Controller
{
    public function index(
        Request $request,
        ArchiveKnowledgeAccess $access,
        ArchiveEventDatePresenter $datePresenter,
        ArchiveLocationPresenter $locationPresenter
    ): View {
        return view('archive.events.index', [
            'events' => $access->events(ArchiveEvent::query(), $request->user())
                ->with('location')
                ->withCount(['mediaItems', 'provenanceLinks'])
                ->orderByDesc('date_year')
                ->orderByDesc('starts_on')
                ->orderBy('name')
                ->paginate(24),
            'datePresenter' => $datePresenter,
            'locationPresenter' => $locationPresenter,
            'canCurate' => $request->user()->isArchiveAdministrator(),
        ]);
    }

    public function create(): View
    {
        return view('archive.events.create', [
            'locations' => $this->acceptedLocations(),
        ]);
    }

    public function store(
        SaveArchiveEventRequest $request,
        ReviewArchiveEvent $action
    ): RedirectResponse {
        $event = $action->create(
            $request->safe()->except('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.events.show', $event)
            ->with('status', "Event {$event->event_id} created.");
    }

    public function show(
        Request $request,
        ArchiveKnowledgeAccess $access,
        ArchiveAccess $archiveAccess,
        ArchiveEvent $archiveEvent,
        ArchiveEventDatePresenter $datePresenter,
        ArchiveLocationPresenter $locationPresenter
    ): View {
        abort_unless($access->canViewEvent($archiveEvent, $request->user()), 404);
        $canCurate = $request->user()->isArchiveAdministrator();
        $archiveEvent->load([
            'location',
            'mediaItems' => fn ($query) => $query->where('review_status', MediaReviewStatus::Approved)->whereNotNull('approved_at')->orderBy('archive_id'),
        ]);
        $archiveEvent->setRelation('mediaItems', $archiveEvent->mediaItems
            ->filter(fn (MediaItem $media) => $archiveAccess->canView($request->user(), $media))
            ->values());

        if ($canCurate) {
            $archiveEvent->load([
                'provenanceLinks' => fn ($query) => $query->with(['sourceCollection', 'scanBatch'])->orderBy('id'),
                'revisions' => fn ($query) => $query->with('actor:id,name')->latest('revision_number'),
            ]);
        }

        return view('archive.events.show', [
            'event' => $archiveEvent,
            'datePresenter' => $datePresenter,
            'locationPresenter' => $locationPresenter,
            'canCurate' => $canCurate,
            'sources' => $canCurate ? SourceCollection::query()
                ->with('scanBatches')
                ->orderBy('name')
                ->get() : collect(),
            'availableMedia' => $canCurate ? MediaItem::query()
                ->select(['id', 'archive_id', 'title'])
                ->where('review_status', MediaReviewStatus::Approved)
                ->whereNotNull('approved_at')
                ->whereDoesntHave('events', fn ($query) => $query->whereKey($archiveEvent->id))
                ->orderBy('archive_id')
                ->get() : collect(),
        ]);
    }

    public function edit(ArchiveEvent $archiveEvent): View
    {
        $this->abortUnlessAccepted($archiveEvent);

        return view('archive.events.edit', [
            'event' => $archiveEvent,
            'locations' => $this->acceptedLocations(),
        ]);
    }

    public function update(
        SaveArchiveEventRequest $request,
        ArchiveEvent $archiveEvent,
        ReviewArchiveEvent $action
    ): RedirectResponse {
        $this->abortUnlessAccepted($archiveEvent);
        $event = $action->update(
            $archiveEvent,
            $request->safe()->except('expected_metadata_revision'),
            (int) $request->validated('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.events.show', $event)
            ->with('status', "Event {$event->event_id} updated.");
    }

    /** @return Collection<int, ArchiveLocation> */
    private function acceptedLocations(): Collection
    {
        return ArchiveLocation::query()
            ->where('review_state', KnowledgeReviewState::Accepted)
            ->orderBy('label')
            ->get();
    }

    private function abortUnlessAccepted(ArchiveEvent $event): void
    {
        abort_unless($event->review_state === KnowledgeReviewState::Accepted, 404);
    }
}
