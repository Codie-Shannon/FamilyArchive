<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Intake\Actions\ApproveIncomingPhotoForRestoration;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Intake\Presenters\IncomingUploadPresenter;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
use App\Domain\Processing\Models\ProcessingJob;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PhotoIntakeController extends Controller
{
    public function index(): View
    {
        return view('admin.photo-intake.index', ['limits' => config('archive.photo_intake')]);
    }

    public function store(Request $request, CreateAndRetainIncomingPhoto $creator): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'file']]);
        $upload = $creator->create($request->user(), $request->file('photo'));

        return redirect()->route('admin.photo-intake.show', $upload)->with('retained_upload', $upload->upload_id);
    }

    public function queue(IncomingUploadPresenter $presenter): View
    {
        $rows = IncomingUpload::query()->latest('submitted_at')->limit(50)->get()->map(fn (IncomingUpload $upload) => $presenter->present($upload));

        return view('admin.photo-intake.queue', ['rows' => $rows]);
    }

    public function show(IncomingUpload $incomingUpload, IncomingUploadPresenter $presenter): View
    {
        $incomingUpload->load('archivePromotion.originalVersion');
        $sourceVersionId = $incomingUpload->archivePromotion?->original_media_file_version_id;

        return view('admin.photo-intake.show', [
            'upload' => $presenter->present($incomingUpload),
            'submission' => ContributorSubmission::query()
                ->where('incoming_upload_id', $incomingUpload->id)
                ->first(),
            'processingJob' => $sourceVersionId === null ? null : ProcessingJob::query()
                ->with('candidate')
                ->where('source_version_id', $sourceVersionId)
                ->latest('id')
                ->first(),
        ]);
    }

    public function approveAndProcess(
        Request $request,
        IncomingUpload $incomingUpload,
        ApproveIncomingPhotoForRestoration $approver,
    ): RedirectResponse {
        $result = $approver->handle($incomingUpload, $request->user());

        if ($result->state === 'duplicate_review') {
            return redirect()->route('admin.duplicate-candidates.index')
                ->with('status', 'An exact match was found. Resolve the duplicate review before acceptance.');
        }

        if ($result->state === 'original_accepted') {
            return redirect()->route('admin.photo-intake.show', $incomingUpload)
                ->with('status', 'The verified original was accepted. Candidate automation is disabled for this submission.');
        }

        return redirect()->route('admin.restoration')
            ->with('status', 'The verified original was preserved and a separate restoration candidate is ready for review.');
    }
}
