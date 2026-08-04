<?php

namespace App\Http\Controllers;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\UploadSession;
use App\Domain\CloudImport\Services\BrowserUploadBatch;
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

    public function start(Request $request, BrowserUploadBatch $batches): RedirectResponse
    {
        abort_unless($request->user()->canContribute(), 403, 'Contributor access is required.');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'source_context' => ['required', 'string', 'max:2000'],
            'expected_files' => ['required', 'integer', 'min:1', 'max:100'],
            'automation_preset' => ['required', Rule::in(['balanced', 'conservative', 'originals', 'custom'])],
            'automation_mode' => ['nullable', Rule::in(['off', 'suggestions', 'candidates'])],
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
        $preferences = $this->automationPreferences($request, $validated);
        $session = DB::transaction(function () use ($request, $validated, $preferences, $batches): UploadSession {
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
            $batches->open($request->user(), $session);

            return $session;
        });

        return redirect()->route('contributor.sessions.show', $session);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, bool|string>
     */
    private function automationPreferences(Request $request, array $validated): array
    {
        $preset = (string) $validated['automation_preset'];
        if ($preset !== 'custom') {
            return match ($preset) {
                'balanced' => [
                    'automation_mode' => 'candidates', 'crop_target' => 'photo_edge',
                    'auto_rotate' => true, 'deskew' => true, 'perspective' => true,
                    'exposure' => true, 'color' => true, 'denoise' => true, 'sharpen' => true,
                    'cleanup' => false, 'damage_repair' => false, 'upscale' => false, 'quality_warnings' => true,
                ],
                'conservative' => [
                    'automation_mode' => 'suggestions', 'crop_target' => 'photo_edge',
                    'auto_rotate' => true, 'deskew' => true, 'perspective' => false,
                    'exposure' => false, 'color' => false, 'denoise' => false, 'sharpen' => false,
                    'cleanup' => false, 'damage_repair' => false, 'upscale' => false, 'quality_warnings' => true,
                ],
                default => [
                    'automation_mode' => 'off', 'crop_target' => 'none',
                    'auto_rotate' => false, 'deskew' => false, 'perspective' => false,
                    'exposure' => false, 'color' => false, 'denoise' => false, 'sharpen' => false,
                    'cleanup' => false, 'damage_repair' => false, 'upscale' => false, 'quality_warnings' => false,
                ],
            };
        }

        return [
            'automation_mode' => (string) ($validated['automation_mode'] ?? 'suggestions'),
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
    }

    public function show(Request $request, UploadSession $session): View
    {
        abort_unless($session->user_id === $request->user()->id || $request->user()->isArchiveAdministrator(), 403);

        $reviewBatchAvailable = $session->cloud_import_session_id !== null
            ? DB::table('cloud_import_sessions')
                ->where('id', $session->cloud_import_session_id)
                ->where('provider', 'manual_export')
                ->exists()
            : DB::table('cloud_import_sessions')
                ->where('session_id', $session->session_id)
                ->where('provider', 'manual_export')
                ->exists();

        return view('contributor.show', [
            'session' => $session->load('submissions.incomingUpload'),
            'reviewBatchAvailable' => $reviewBatchAvailable,
        ]);
    }

    public function upload(Request $request, UploadSession $session, CreateAndRetainIncomingPhoto $creator, BrowserUploadBatch $batches): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id && $request->user()->canContribute(), 403);
        abort_if($session->expires_at->isPast(), 410, 'This upload session has expired.');
        abort_if($session->status === 'complete', 422, 'This upload session is already complete.');
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:25'],
            'photos.*' => ['required', 'file'],
        ]);

        foreach ($validated['photos'] as $photo) {
            $incoming = $creator->create($request->user(), $photo);
            $submission = ContributorSubmission::query()->create([
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
            $batches->retain($session, $submission, $incoming);
        }
        $updated = $batches->checkpoint($session);
        $message = $updated->status === 'complete'
            ? ($request->user()->canManageTrustedIntake()
                ? 'Batch retained and ready for your review.'
                : 'Batch retained and ready for trusted review.')
            : 'Files retained safely. Add more photos or finish this batch when ready.';

        return back()->with('status', $message);
    }

    public function finish(Request $request, UploadSession $session, BrowserUploadBatch $batches): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id && $request->user()->canContribute(), 403);
        abort_if($session->status === 'complete', 422, 'This upload session is already complete.');
        $batches->checkpoint($session, true);

        return back()->with('status', $request->user()->canManageTrustedIntake()
            ? 'Batch finished and ready for your review.'
            : 'Batch finished and ready for trusted review.');
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
