<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Duplicates\Actions\ResolveDuplicateCandidate;
use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateReviewDecision;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DuplicateCandidateController extends Controller
{
    public function index(): View
    {
        $base = fn () => DuplicateCandidate::query()
            ->with(['incomingUpload:id,upload_id', 'matchedIncomingUpload:id,upload_id', 'matchedMediaFileVersion:id,media_item_id,version_type'])
            ->orderBy('detected_at')->orderBy('id');

        $pendingCandidates = $base()->where('review_state', DuplicateCandidateReviewState::PendingReview->value)->get();
        $resolvedCandidates = $base()->where('review_state', DuplicateCandidateReviewState::Resolved->value)->orderByDesc('reviewed_at')->get();

        return view('admin.duplicate-candidates.index', compact('pendingCandidates', 'resolvedCandidates'));
    }

    public function show(DuplicateCandidate $candidate): View
    {
        $candidate->load(['incomingUpload', 'matchedIncomingUpload', 'matchedMediaFileVersion.mediaItem', 'reviewEvents.actor']);

        return view('admin.duplicate-candidates.show', [
            'candidate' => $candidate,
            'decisions' => DuplicateReviewDecision::cases(),
        ]);
    }

    public function resolve(Request $request, DuplicateCandidate $candidate, ResolveDuplicateCandidate $action): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::enum(DuplicateReviewDecision::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->handle(
            $candidate,
            DuplicateReviewDecision::from($validated['decision']),
            $validated['reason'] ?? null,
            $request->user(),
            ['route' => $request->route()?->getName(), 'method' => $request->method()],
        );

        return redirect()->route('admin.duplicate-candidates.show', $candidate)->with('status', 'Duplicate review decision recorded. Retained source bytes were not changed.');
    }
}
