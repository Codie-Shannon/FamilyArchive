<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class DuplicateCandidateController extends Controller
{
    public function index(): View
    {
        $candidates = DuplicateCandidate::query()
            ->with(['incomingUpload:id,upload_id', 'matchedIncomingUpload:id,upload_id', 'matchedMediaFileVersion:id,media_item_id,version_type'])
            ->where('review_state', DuplicateCandidateReviewState::PendingReview->value)
            ->orderBy('detected_at')->orderBy('id')->get();

        return view('admin.duplicate-candidates.index', compact('candidates'));
    }

    public function show(DuplicateCandidate $candidate): View
    {
        $candidate->load(['incomingUpload', 'matchedIncomingUpload', 'matchedMediaFileVersion.mediaItem']);

        return view('admin.duplicate-candidates.show', compact('candidate'));
    }
}
