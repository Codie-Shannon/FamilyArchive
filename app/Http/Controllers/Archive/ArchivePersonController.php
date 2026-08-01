<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\ReviewArchivePerson;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\Presenters\ArchivePersonPresenter;
use App\Domain\Knowledge\Presenters\FamilyBranchPresenter;
use App\Domain\Knowledge\Services\ArchiveKnowledgeAccess;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\SaveArchivePersonRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ArchivePersonController extends Controller
{
    public function index(
        Request $request,
        ArchiveKnowledgeAccess $access,
        ArchivePersonPresenter $personPresenter,
        FamilyBranchPresenter $branchPresenter
    ): View {
        return view('archive.people.index', [
            'people' => $access->people(ArchivePerson::query(), $request->user())
                ->with('familyBranch')
                ->withCount('provenanceLinks')
                ->orderBy('is_private')
                ->orderBy('display_name')
                ->paginate(24),
            'personPresenter' => $personPresenter,
            'branchPresenter' => $branchPresenter,
            'canCurate' => $request->user()->isArchiveAdministrator(),
        ]);
    }

    public function create(): View
    {
        return view('archive.people.create', [
            'branches' => $this->acceptedBranches(),
        ]);
    }

    public function store(
        SaveArchivePersonRequest $request,
        ReviewArchivePerson $action
    ): RedirectResponse {
        $person = $action->create($request->personInput(), $request->user());

        return redirect()
            ->route('archive.people.show', $person)
            ->with('status', "Person {$person->person_id} created.");
    }

    public function show(
        Request $request,
        ArchiveKnowledgeAccess $access,
        ArchivePerson $archivePerson,
        ArchivePersonPresenter $personPresenter,
        FamilyBranchPresenter $branchPresenter
    ): View {
        abort_unless($access->canViewPerson($archivePerson, $request->user()), 404);
        $canCurate = $request->user()->isArchiveAdministrator();
        $archivePerson->load('familyBranch');

        if ($canCurate) {
            $archivePerson->load([
                'familyBranch.revisions' => fn ($query) => $query->with('actor:id,name')->latest('revision_number'),
                'provenanceLinks' => fn ($query) => $query->with(['sourceCollection', 'scanBatch'])->orderBy('id'),
                'revisions' => fn ($query) => $query->with('actor:id,name')->latest('revision_number'),
            ]);
        }

        return view('archive.people.show', [
            'person' => $archivePerson,
            'personPresenter' => $personPresenter,
            'branchPresenter' => $branchPresenter,
            'canCurate' => $canCurate,
            'sources' => $canCurate ? SourceCollection::query()
                ->with('scanBatches')
                ->orderBy('name')
                ->get() : collect(),
        ]);
    }

    public function edit(ArchivePerson $archivePerson): View
    {
        $this->abortUnlessAccepted($archivePerson);

        return view('archive.people.edit', [
            'person' => $archivePerson,
            'branches' => $this->acceptedBranches(),
        ]);
    }

    public function update(
        SaveArchivePersonRequest $request,
        ArchivePerson $archivePerson,
        ReviewArchivePerson $action
    ): RedirectResponse {
        $this->abortUnlessAccepted($archivePerson);
        $person = $action->update(
            $archivePerson,
            $request->personInput(),
            (int) $request->validated('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.people.show', $person)
            ->with('status', "Person {$person->person_id} updated.");
    }

    /** @return Collection<int, FamilyBranch> */
    private function acceptedBranches(): Collection
    {
        return FamilyBranch::query()
            ->where('review_state', KnowledgeReviewState::Accepted)
            ->orderBy('name')
            ->get();
    }

    private function abortUnlessAccepted(ArchivePerson $person): void
    {
        abort_unless(
            $person->review_state === KnowledgeReviewState::Accepted
            && $person->identity_state === 'confirmed',
            404
        );
    }
}
