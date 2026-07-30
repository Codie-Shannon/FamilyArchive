<?php

namespace App\Http\Controllers;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\UploadSession;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ContributorSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canContribute(), 403, 'Contributor access is required.');

        return view('contributor.index', [
            'sessions' => UploadSession::query()
                ->where('user_id', $request->user()->id)
                ->with('submissions')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canContribute(), 403, 'Contributor access is required.');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'source_context' => ['required', 'string', 'max:2000'],
            'expected_files' => ['required', 'integer', 'min:1', 'max:100'],
            'automation_mode' => ['required', Rule::in(['off', 'suggestions', 'candidates'])],
            'auto_rotate' => ['nullable', 'boolean'],
            'deskew' => ['nullable', 'boolean'],
            'perspective' => ['nullable', 'boolean'],
            'crop_target' => ['nullable', Rule::in(['none', 'photo_edge', 'content'])],
            'exposure' => ['nullable', 'boolean'],
            'color' => ['nullable', 'boolean'],
            'denoise' => ['nullable', 'boolean'],
            'sharpen' => ['nullable', 'boolean'],
            'cleanup' => ['nullable', 'boolean'],
            'damage_repair' => ['nullable', 'boolean'],
            'upscale' => ['nullable', 'boolean'],
            'quality_warnings' => ['nullable', 'boolean'],
        ]);
        $preferences = [
            'automation_mode' => $validated['automation_mode'],
            'auto_rotate' => $request->boolean('auto_rotate'),
            'deskew' => $request->boolean('deskew'),
            'perspective' => $request->boolean('perspective'),
            'crop_target' => $validated['crop_target'] ?? 'none',
            'exposure' => $request->boolean('exposure'),
            'color' => $request->boolean('color'),
            'denoise' => $request->boolean('denoise'),
            'sharpen' => $request->boolean('sharpen'),
            'cleanup' => $request->boolean('cleanup'),
            'damage_repair' => $request->boolean('damage_repair'),
            'upscale' => $request->boolean('upscale'),
            'quality_warnings' => $request->boolean('quality_warnings'),
        ];
        $session = UploadSession::query()->create([
            'session_id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'source_context' => $validated['source_context'],
            'automation_preferences' => $preferences,
            'expected_files' => $validated['expected_files'],
            'received_files' => 0,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ]);

        return redirect()->route('contributor.sessions.show', $session);
    }

    public function show(Request $request, UploadSession $session): View
    {
        abort_unless($session->user_id === $request->user()->id || $request->user()->isArchiveAdministrator(), 403);

        return view('contributor.show', ['session' => $session->load('submissions.incomingUpload')]);
    }

    public function upload(Request $request, UploadSession $session, CreateAndRetainIncomingPhoto $creator): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id && $request->user()->canContribute(), 403);
        abort_if($session->expires_at->isPast(), 410, 'This upload session has expired.');
        abort_if($session->status === 'complete', 422, 'This upload session is already complete.');
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'file'],
        ]);

        foreach ($validated['photos'] as $photo) {
            $incoming = $creator->create($request->user(), $photo);
            ContributorSubmission::query()->create([
                'submission_id' => 'SUB-'.Str::upper(Str::random(12)),
                'user_id' => $request->user()->id,
                'upload_session_id' => $session->id,
                'incoming_upload_id' => $incoming->id,
                'status' => 'retained',
                'original_name' => $incoming->original_filename,
                'source_context' => $session->source_context,
                'proposed_metadata' => ['session_title' => $session->title],
                'automation_preferences' => $session->automation_preferences,
            ]);
        }

        DB::transaction(function () use ($session, $validated): void {
            $locked = UploadSession::query()->lockForUpdate()->findOrFail($session->id);
            $locked->received_files += count($validated['photos']);
            $locked->status = $locked->received_files >= $locked->expected_files ? 'complete' : 'paused';
            $locked->save();
        });

        return back()->with('status', 'Files retained in quarantine. The owner moderation queue has been updated.');
    }

    public function review(Request $request, ContributorSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['possible_duplicate', 'needs_info', 'accepted', 'rejected'])],
            'reviewer_note' => ['required', 'string', 'max:2000'],
        ]);
        $submission->forceFill([
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return back()->with('status', 'Contributor submission moderated.');
    }
}
