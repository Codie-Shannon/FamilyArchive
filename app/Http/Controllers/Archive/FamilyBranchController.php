<?php

namespace App\Http\Controllers\Archive;

use App\Domain\Knowledge\Actions\ReviewFamilyBranch;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\Presenters\ArchivePersonPresenter;
use App\Domain\Knowledge\Presenters\FamilyBranchPresenter;
use App\Domain\Provenance\Models\SourceCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Archive\SaveFamilyBranchRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class FamilyBranchController extends Controller
{
    public function index(FamilyBranchPresenter $presenter): View
    {
        return view('archive.branches.index', [
            'branches' => FamilyBranch::query()
                ->where('review_state', KnowledgeReviewState::Accepted)
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
        FamilyBranch $familyBranch,
        FamilyBranchPresenter $branchPresenter,
        ArchivePersonPresenter $personPresenter
    ): View {
        $this->abortUnlessAccepted($familyBranch);
        $familyBranch->load([
            'people' => fn ($query) => $query
                ->where('review_state', KnowledgeReviewState::Accepted)
                ->where('identity_state', 'confirmed')
                ->orderBy('is_private')
                ->orderBy('display_name'),
            'provenanceLinks' => fn ($query) => $query
                ->with(['sourceCollection', 'scanBatch'])
                ->orderBy('id'),
            'revisions' => fn ($query) => $query
                ->with('actor:id,name')
                ->latest('revision_number'),
        ]);

        return view('archive.branches.show', [
            'branch' => $familyBranch,
            'branchPresenter' => $branchPresenter,
            'personPresenter' => $personPresenter,
            'sources' => SourceCollection::query()
                ->with('scanBatches')
                ->orderBy('name')
                ->get(),
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
