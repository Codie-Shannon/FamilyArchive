<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\ReviewArchiveLocation;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Presenters\ArchiveLocationPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\SaveArchiveLocationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ArchiveLocationController extends Controller
{
    public function index(ArchiveLocationPresenter $presenter): View
    {
        return view('archive.locations.index', [
            'locations' => ArchiveLocation::query()
                ->where('review_state', KnowledgeReviewState::Accepted)
                ->withCount([
                    'events' => fn ($query) => $query
                        ->where('review_state', KnowledgeReviewState::Accepted),
                ])
                ->orderBy('label')
                ->paginate(24),
            'presenter' => $presenter,
        ]);
    }

    public function create(): View
    {
        return view('archive.locations.create');
    }

    public function store(
        SaveArchiveLocationRequest $request,
        ReviewArchiveLocation $action
    ): RedirectResponse {
        $location = $action->create(
            $request->locationInput(),
            $request->user()
        );

        return redirect()
            ->route('archive.locations.show', $location)
            ->with('status', "Location {$location->location_id} created.");
    }

    public function show(
        ArchiveLocation $archiveLocation,
        ArchiveLocationPresenter $presenter
    ): View {
        $this->abortUnlessAccepted($archiveLocation);
        $archiveLocation->load([
            'events' => fn ($query) => $query
                ->where('review_state', KnowledgeReviewState::Accepted)
                ->orderBy('name'),
            'revisions' => fn ($query) => $query
                ->with('actor:id,name')
                ->latest('revision_number'),
        ]);

        return view('archive.locations.show', [
            'location' => $archiveLocation,
            'presenter' => $presenter,
        ]);
    }

    public function edit(ArchiveLocation $archiveLocation): View
    {
        $this->abortUnlessAccepted($archiveLocation);

        return view('archive.locations.edit', [
            'location' => $archiveLocation,
        ]);
    }

    public function update(
        SaveArchiveLocationRequest $request,
        ArchiveLocation $archiveLocation,
        ReviewArchiveLocation $action
    ): RedirectResponse {
        $this->abortUnlessAccepted($archiveLocation);
        $location = $action->update(
            $archiveLocation,
            $request->locationInput(),
            (int) $request->validated('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.locations.show', $location)
            ->with('status', "Location {$location->location_id} updated.");
    }

    private function abortUnlessAccepted(ArchiveLocation $location): void
    {
        abort_unless($location->review_state === KnowledgeReviewState::Accepted, 404);
    }
}
