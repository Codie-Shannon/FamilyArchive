<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Archive\Actions\PromoteIncomingPhoto;
use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ArchivePromotionController extends Controller
{
    public function index(PromoteIncomingPhoto $promoter): View
    {
        $uploads = IncomingUpload::query()
            ->with(['mediaItem.fileVersions'])
            ->where('source_file_retained', true)
            ->latest('submitted_at')
            ->limit(100)
            ->get();

        $eligible = $uploads->filter(fn (IncomingUpload $upload): bool => $upload->media_item_id === null && $promoter->isEligible($upload));
        $blocked = $uploads->filter(fn (IncomingUpload $upload): bool => $upload->media_item_id === null && ! $promoter->isEligible($upload));
        $promoted = ArchivePromotion::query()
            ->with(['incomingUpload', 'mediaItem', 'originalVersion'])
            ->latest('promoted_at')
            ->limit(50)
            ->get();

        return view('admin.archive-promotions.index', compact('eligible', 'blocked', 'promoted'));
    }

    public function show(IncomingUpload $incomingUpload, PromoteIncomingPhoto $promoter): View
    {
        $incomingUpload->load(['mediaItem.fileVersions', 'reviewer']);
        $promotion = ArchivePromotion::query()
            ->with(['mediaItem', 'originalVersion', 'actor'])
            ->where('incoming_upload_id', $incomingUpload->id)
            ->first();

        return view('admin.archive-promotions.show', [
            'upload' => $incomingUpload,
            'promotion' => $promotion,
            'eligible' => $promotion === null && $promoter->isEligible($incomingUpload),
            'eligibleStatuses' => [
                DuplicateStatus::NoMatch->value,
                DuplicateStatus::NotDuplicate->value,
                DuplicateStatus::RelatedButDistinct->value,
            ],
        ]);
    }

    public function store(Request $request, IncomingUpload $incomingUpload, PromoteIncomingPhoto $promoter): RedirectResponse
    {
        $promotion = $promoter->handle($incomingUpload, $request->user());

        return redirect()
            ->route('admin.archive-promotions.show', $promotion->incoming_upload_id)
            ->with('status', 'Archive acceptance completed. The original was copied and verified; the quarantine source remains retained.');
    }
}
