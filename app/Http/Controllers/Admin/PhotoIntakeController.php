<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Intake\Actions\ApproveIncomingPhotoForRestoration;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Intake\Presenters\IncomingUploadPresenter;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
use App\Domain\Processing\Models\ProcessingJob;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $rows = IncomingUpload::query()
            ->whereNotIn('review_status', [IncomingReviewStatus::Approved, IncomingReviewStatus::Rejected])
            ->latest('submitted_at')
            ->limit(50)
            ->get()
            ->map(fn (IncomingUpload $upload) => $presenter->present($upload));

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

    public function preview(IncomingUpload $incomingUpload): StreamedResponse
    {
        if (
            ! $incomingUpload->source_file_retained
            || ! is_string($incomingUpload->incoming_path)
            || ! is_string($incomingUpload->sha256)
            || ! array_key_exists($incomingUpload->mime_type, config('archive.photo_intake.mime_extensions', []))
        ) {
            abort(404);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_quarantine');
        if (! $disk->exists($incomingUpload->incoming_path)) {
            abort(404);
        }

        $verification = $disk->readStream($incomingUpload->incoming_path);
        if (! is_resource($verification)) {
            abort(404);
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            while (! feof($verification)) {
                $chunk = fread($verification, 1024 * 1024);
                if ($chunk === false) {
                    abort(404);
                }
                $bytes += strlen($chunk);
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($verification);
        }

        if (
            $bytes !== $incomingUpload->file_size_bytes
            || ! hash_equals(strtolower($incomingUpload->sha256), strtolower(hash_final($hash)))
        ) {
            abort(404);
        }

        $path = $incomingUpload->incoming_path;

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return;
            }

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $incomingUpload->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
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

        if ($result->candidate === null) {
            abort(409, 'The original was accepted, but its edit preview is unavailable.');
        }

        return redirect()->route('admin.restoration', ['candidate' => $result->candidate->id])
            ->with('status', 'Original accepted. Compare it with the generated edit before choosing the viewing version.');
    }
}
