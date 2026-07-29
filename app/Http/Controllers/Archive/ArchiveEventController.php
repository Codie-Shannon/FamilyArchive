<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\ReviewArchiveEvent;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Presenters\ArchiveEventDatePresenter;
use App\Domain\Knowledge\Presenters\ArchiveLocationPresenter;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\SaveArchiveEventRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;

final class ArchiveEventController extends Controller
{
    public function index(
        ArchiveEventDatePresenter $datePresenter,
        ArchiveLocationPresenter $locationPresenter
    ): View {
        return view('archive.events.index', [
            'events' => ArchiveEvent::query()
                ->where('review_state', KnowledgeReviewState::Accepted)
                ->with('location')
                ->withCount(['mediaItems', 'provenanceLinks'])
                ->orderByDesc('date_year')
                ->orderByDesc('starts_on')
                ->orderBy('name')
                ->paginate(24),
            'datePresenter' => $datePresenter,
            'locationPresenter' => $locationPresenter,
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
        ArchiveEvent $archiveEvent,
        ArchiveEventDatePresenter $datePresenter,
        ArchiveLocationPresenter $locationPresenter
    ): View {
        $this->abortUnlessAccepted($archiveEvent);
        $archiveEvent->load([
            'location',
            'provenanceLinks' => fn ($query) => $query
                ->with(['sourceCollection', 'scanBatch'])
                ->orderBy('id'),
            'mediaItems' => fn ($query) => $query
                ->where('review_status', MediaReviewStatus::Approved)
                ->whereNotNull('approved_at')
                ->orderBy('archive_id'),
            'revisions' => fn ($query) => $query
                ->with('actor:id,name')
                ->latest('revision_number'),
        ]);

        return view('archive.events.show', [
            'event' => $archiveEvent,
            'datePresenter' => $datePresenter,
            'locationPresenter' => $locationPresenter,
            'sources' => SourceCollection::query()
                ->with('scanBatches')
                ->orderBy('name')
                ->get(),
            'availableMedia' => MediaItem::query()
                ->select(['id', 'archive_id', 'title'])
                ->where('review_status', MediaReviewStatus::Approved)
                ->whereNotNull('approved_at')
                ->whereDoesntHave('events', fn ($query) => $query->whereKey($archiveEvent->id))
                ->orderBy('archive_id')
                ->get(),
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
