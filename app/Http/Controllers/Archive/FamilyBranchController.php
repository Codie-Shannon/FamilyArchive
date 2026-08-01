<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\ReviewFamilyBranch;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\Presenters\ArchivePersonPresenter;
use App\Domain\Knowledge\Presenters\FamilyBranchPresenter;
use App\Domain\Knowledge\Services\ArchiveKnowledgeAccess;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\SaveFamilyBranchRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class FamilyBranchController extends Controller
{
    public function index(Request $request, ArchiveKnowledgeAccess $access, FamilyBranchPresenter $presenter): View
    {
        return view('archive.branches.index', [
            'branches' => $access->branches(FamilyBranch::query(), $request->user())
                ->withCount([
                    'people' => fn ($query) => $query
                        ->where('review_state', KnowledgeReviewState::Accepted)
                        ->where('identity_state', 'confirmed'),
                    'provenanceLinks',
                ])
                ->orderBy('is_sensitive')
                ->orderBy('name')
                ->paginate(24),
            'presenter' => $presenter,
            'canCurate' => $request->user()->isArchiveAdministrator(),
        ]);
    }

    public function create(): View
    {
        return view('archive.branches.create');
    }

    public function store(
        SaveFamilyBranchRequest $request,
        ReviewFamilyBranch $action
    ): RedirectResponse {
        $branch = $action->create($request->branchInput(), $request->user());

        return redirect()
            ->route('archive.branches.show', $branch)
            ->with('status', "Family branch {$branch->branch_id} created.");
    }

    public function show(
        Request $request,
        ArchiveKnowledgeAccess $access,
        FamilyBranch $familyBranch,
        FamilyBranchPresenter $branchPresenter,
        ArchivePersonPresenter $personPresenter
    ): View {
        abort_unless($access->canViewBranch($familyBranch, $request->user()), 404);
        $canCurate = $request->user()->isArchiveAdministrator();
        $familyBranch->setRelation(
            'people',
            $access->people(
                ArchivePerson::query()->where('family_branch_id', $familyBranch->getKey()),
                $request->user(),
            )->orderBy('display_name')->get(),
        );

        if ($canCurate) {
            $familyBranch->load([
                'provenanceLinks' => fn ($query) => $query->with(['sourceCollection', 'scanBatch'])->orderBy('id'),
                'revisions' => fn ($query) => $query->with('actor:id,name')->latest('revision_number'),
            ]);
        }

        return view('archive.branches.show', [
            'branch' => $familyBranch,
            'branchPresenter' => $branchPresenter,
            'personPresenter' => $personPresenter,
            'canCurate' => $canCurate,
            'sources' => $canCurate ? SourceCollection::query()
                ->with('scanBatches')
                ->orderBy('name')
                ->get() : collect(),
        ]);
    }

    public function edit(FamilyBranch $familyBranch): View
    {
        $this->abortUnlessAccepted($familyBranch);

        return view('archive.branches.edit', [
            'branch' => $familyBranch,
        ]);
    }

    public function update(
        SaveFamilyBranchRequest $request,
        FamilyBranch $familyBranch,
        ReviewFamilyBranch $action
    ): RedirectResponse {
        $this->abortUnlessAccepted($familyBranch);
        $branch = $action->update(
            $familyBranch,
            $request->branchInput(),
            (int) $request->validated('expected_metadata_revision'),
            $request->user()
        );

        return redirect()
            ->route('archive.branches.show', $branch)
            ->with('status', "Family branch {$branch->branch_id} updated.");
    }

    private function abortUnlessAccepted(FamilyBranch $branch): void
    {
        abort_unless($branch->review_state === KnowledgeReviewState::Accepted, 404);
    }
}
