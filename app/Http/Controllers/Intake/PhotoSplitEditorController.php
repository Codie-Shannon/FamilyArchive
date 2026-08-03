<?php

namespace App\Http\Controllers\Intake;

use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Domain\Processing\Services\PhotoSplitReviewService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PhotoSplitEditorController extends Controller
{
    public function edit(
        Request $request,
        string $sessionId,
        int $itemId,
        TrustedBatchReview $reviews,
        PhotoSplitReviewService $splits,
    ): View {
        [$session, $item] = $this->item($reviews, $request, $sessionId, $itemId);
        abort_if(data_get($item, 'review_decision') !== null, 409, 'Reviewed items cannot be changed.');

        $proposal = $splits->analyzeItem($itemId, $request->user(), true);
        abort_unless($proposal instanceof PhotoSplitProposal, 404);
        $proposal->load(['regions.candidateVersion', 'sourceVersion']);

        return view('intake.photo-split-editor', compact('session', 'item', 'proposal'));
    }

    public function update(
        Request $request,
        string $sessionId,
        int $itemId,
        TrustedBatchReview $reviews,
        PhotoSplitReviewService $splits,
    ): RedirectResponse {
        [, $item] = $this->item($reviews, $request, $sessionId, $itemId);
        abort_if(data_get($item, 'review_decision') !== null, 409, 'Reviewed items cannot be changed.');
        $validated = $request->validate(['regions_json' => ['required', 'string', 'max:50000']]);
        $regions = json_decode($validated['regions_json'], true);
        if (! is_array($regions)) {
            throw ValidationException::withMessages(['regions_json' => 'The split regions could not be read.']);
        }

        $proposal = PhotoSplitProposal::query()->where('cloud_import_item_id', $itemId)->firstOrFail();
        $splits->saveRegions($proposal, $request->user(), $regions);

        return redirect()
            ->route('intake.items.split', [$sessionId, $itemId])
            ->with('status', 'Split previews saved. The original remains unchanged; review every included photo before publishing.');
    }

    /** @return array{0:object,1:object} */
    private function item(TrustedBatchReview $reviews, Request $request, string $sessionId, int $itemId): array
    {
        $session = $reviews->session($sessionId, $request->user());
        $item = DB::table('cloud_import_items')
            ->where('id', $itemId)
            ->where('cloud_import_session_id', data_get($session, 'id'))
            ->where('state', 'retained')
            ->first();
        abort_unless($item !== null, 404);
        abort_if(data_get($item, 'prepared_at') === null, 409, 'Prepare the immutable original before splitting it.');

        return [$session, $item];
    }
}
