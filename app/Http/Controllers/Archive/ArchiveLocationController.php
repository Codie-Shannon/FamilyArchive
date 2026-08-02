<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\ReviewArchiveLocation;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Presenters\ArchiveLocationPresenter;
use App\Domain\Knowledge\Services\ArchiveKnowledgeAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\SaveArchiveLocationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ArchiveLocationController extends Controller
{
    public function index(Request $request, ArchiveKnowledgeAccess $access, ArchiveLocationPresenter $presenter): View
    {
        return view('archive.locations.index', [
            'locations' => $access->locations(ArchiveLocation::query(), $request->user())
                ->withCount([
                    'events' => fn ($query) => $query
                        ->where('review_state', KnowledgeReviewState::Accepted),
                ])
                ->orderBy('label')
                ->paginate(24),
            'presenter' => $presenter,
            'canCurate' => $request->user()->isArchiveAdministrator(),
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
        Request $request,
        ArchiveKnowledgeAccess $access,
        ArchiveLocation $archiveLocation,
        ArchiveLocationPresenter $presenter
    ): View {
        abort_unless($access->canViewLocation($archiveLocation, $request->user()), 404);
        $canCurate = $request->user()->isArchiveAdministrator();
        $archiveLocation->setRelation(
            'events',
            $access->events(
                ArchiveEvent::query()->where('archive_location_id', $archiveLocation->id),
                $request->user()
            )->orderBy('name')->get()
        );

        if ($canCurate) {
            $archiveLocation->load(['revisions' => fn ($query) => $query->with('actor:id,name')->latest('revision_number')]);
        }

        return view('archive.locations.show', [
            'location' => $archiveLocation,
            'presenter' => $presenter,
            'canCurate' => $canCurate,
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
