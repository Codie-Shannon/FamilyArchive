<?php

namespace App\Http\Controllers\Intake;

use App\Domain\CloudImport\Services\BatchContentSafety;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BatchReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = DB::table('cloud_import_sessions')
            ->where('provider', 'manual_export')
            ->whereIn('state', ['paused', 'complete'])
            ->latest('created_at');
        if (! $user->isArchiveAdministrator()) {
            $query->where('user_id', $user->id);
        }

        $sessions = $query->limit(30)->get()->map(function (object $session): object {
            $session->manifest = json_decode((string) $session->source_manifest, true) ?: [];
            $session->pending_count = max(0, (int) $session->imported_count - (int) $session->reviewed_count);

            return $session;
        });

        return view('intake.index', [
            'sessions' => $sessions,
            'reviewRole' => $user->role === 'owner' ? 'Policy owner' : ($user->role === 'admin' ? 'Archive administrator' : 'Trusted contributor'),
        ]);
    }

    public function show(
        Request $request,
        string $sessionId,
        TrustedBatchReview $reviews,
        BatchContentSafety $safety,
    ): View {
        $session = $reviews->session($sessionId, $request->user());
        $filter = in_array($request->string('filter')->toString(), ['all', 'attention', 'pending', 'reviewed'], true)
            ? $request->string('filter')->toString()
            : 'pending';
        $query = DB::table('cloud_import_items as items')
            ->leftJoin('restoration_candidates as candidates', 'candidates.id', '=', 'items.restoration_candidate_id')
            ->leftJoin('photo_split_proposals as splits', 'splits.cloud_import_item_id', '=', 'items.id')
            ->where('items.cloud_import_session_id', data_get($session, 'id'))
            ->where('items.state', 'retained')
            ->select([
                'items.*',
                'candidates.review_state as candidate_review_state',
                'candidates.analysis as candidate_analysis',
                'splits.id as split_proposal_id',
                'splits.state as split_state',
                'splits.confidence as split_confidence',
            ])
            ->selectSub(function ($query): void {
                $query->from('photo_split_regions as split_regions')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('split_regions.photo_split_proposal_id', 'splits.id')
                    ->where('split_regions.review_state', 'included');
            }, 'split_region_count')
            ->orderBy('items.position');
        match ($filter) {
            'attention' => $query->whereNotNull('items.attention_code'),
            'reviewed' => $query->whereNotNull('items.review_decision'),
            'pending' => $query->whereNull('items.review_decision'),
            default => null,
        };
        $items = $query->paginate(24)->withQueryString();
        $safetyByItem = $items->getCollection()
            ->mapWithKeys(fn (object $item): array => [(int) data_get($item, 'id') => $safety->classification($item)])
            ->all();
        $preparedCount = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', data_get($session, 'id'))
            ->where('state', 'retained')
            ->whereNotNull('prepared_at')
            ->count();
        $manifest = json_decode((string) data_get($session, 'source_manifest', ''), true) ?: [];
        $safetyPolicy = $safety->policy($session);
        $safetyLabels = BatchContentSafety::labels();

        return view('intake.batch-review', compact(
            'session',
            'manifest',
            'items',
            'filter',
            'preparedCount',
            'safetyPolicy',
            'safetyLabels',
            'safetyByItem',
        ));
    }

    public function prepare(Request $request, string $sessionId, TrustedBatchReview $reviews): RedirectResponse
    {
        $result = $reviews->prepare($sessionId, $request->user(), 25);

        return back()->with('status', "Prepared {$result['prepared']} items; {$result['remaining']} remain and {$result['attention']} need attention.");
    }

    public function regenerate(Request $request, string $sessionId, TrustedBatchReview $reviews): RedirectResponse
    {
        $result = $reviews->regeneratePending($sessionId, $request->user(), 25);

        return back()->with('status', "Regenerated {$result['regenerated']} pending suggestions; {$result['failed']} failed and {$result['attention']} need attention.");
    }

    public function updateSafetyPolicy(
        Request $request,
        string $sessionId,
        TrustedBatchReview $reviews,
        BatchContentSafety $safety,
    ): RedirectResponse {
        $validated = $request->validate([
            'block_identification_documents' => ['required', 'boolean'],
            'block_sensitive_minor_images' => ['required', 'boolean'],
        ]);
        $session = $reviews->session($sessionId, $request->user());
        $safety->updatePolicy(
            $session,
            $request->user(),
            (bool) $validated['block_identification_documents'],
            (bool) $validated['block_sensitive_minor_images'],
        );

        return back()->with('status', 'Batch safety policy updated. The permanent illegal-content safeguard remains enabled.');
    }

    public function decide(
        Request $request,
        string $sessionId,
        TrustedBatchReview $reviews,
        BatchContentSafety $safety,
    ): RedirectResponse {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['integer', 'distinct'],
            'decision' => ['required', 'in:'.implode(',', TrustedBatchReview::DECISIONS)],
            'safety' => ['sometimes', 'array'],
            'safety.*.classification' => ['nullable', 'in:'.implode(',', BatchContentSafety::classifications())],
            'safety.*.document_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
        ]);
        $session = $reviews->session($sessionId, $request->user());
        $safety->classifySelected($session, $request->user(), $validated['safety'] ?? [], $validated['items']);
        $result = $reviews->decide($sessionId, $request->user(), $validated['items'], $validated['decision']);

        return back()->with('status', "Reviewed {$result['reviewed']} items; {$result['failed']} were isolated for attention.");
    }
}
