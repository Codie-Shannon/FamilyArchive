<?php

namespace App\Http\Controllers\Intake;

use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Domain\Processing\Services\ManualRestorationEditor;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RestorationEditorController extends Controller
{
    public function edit(
        Request $request,
        string $sessionId,
        int $itemId,
        TrustedBatchReview $reviews,
    ): View {
        $session = $reviews->session($sessionId, $request->user());
        $item = DB::table('cloud_import_items')
            ->where('id', $itemId)
            ->where('cloud_import_session_id', data_get($session, 'id'))
            ->where('state', 'retained')
            ->first();
        abort_unless($item !== null, 404);
        abort_if(data_get($item, 'review_decision') !== null, 409, 'Reviewed items cannot be changed.');

        $candidateId = data_get($item, 'restoration_candidate_id');
        $candidate = $candidateId === null ? null : RestorationCandidate::query()
            ->with(['sourceVersion', 'candidateVersion'])
            ->whereKey((int) $candidateId)
            ->first();
        abort_if($candidate instanceof RestorationCandidate && $candidate->review_state !== 'pending', 409, 'The current review version can no longer be edited.');

        return view('intake.restoration-editor', compact('session', 'item', 'candidate'));
    }

    public function update(
        Request $request,
        string $sessionId,
        int $itemId,
        TrustedBatchReview $reviews,
        ManualRestorationEditor $editor,
    ): RedirectResponse {
        $session = $reviews->session($sessionId, $request->user());
        $validated = $request->validate([
            'orient' => ['nullable', 'boolean'],
            'quarter_turn' => ['required', 'integer', 'between:-2,2'],
            'straighten' => ['required', 'numeric', 'between:-8,8'],
            'crop_left' => ['required', 'numeric', 'between:0,80'],
            'crop_top' => ['required', 'numeric', 'between:0,80'],
            'crop_right' => ['required', 'numeric', 'between:0,80'],
            'crop_bottom' => ['required', 'numeric', 'between:0,80'],
            'brightness' => ['required', 'integer', 'between:-40,40'],
            'contrast' => ['required', 'integer', 'between:-30,30'],
            'red' => ['required', 'integer', 'between:-20,20'],
            'green' => ['required', 'integer', 'between:-20,20'],
            'blue' => ['required', 'integer', 'between:-20,20'],
            'denoise' => ['required', 'integer', 'between:0,3'],
            'sharpen' => ['required', 'integer', 'between:0,2'],
            'cleanup' => ['required', 'integer', 'between:0,3'],
        ]);
        $validated['orient'] = $request->boolean('orient');
        $editor->save($session, $itemId, $request->user(), $validated);

        return redirect()
            ->route('intake.batches.show', [$sessionId, 'filter' => 'pending'])
            ->with('status', 'Your edited version was saved. The archival original is unchanged; review the new version before accepting it.');
    }
}
