<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\ProcessingJobEvent;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Domain\Processing\Services\GdRestorationCandidateProcessor;
use App\Domain\Processing\Services\RestorationReviewService;
use App\Domain\Processing\Services\RestorationWorkflow;
use App\Domain\Storage\Services\ArchiveProviderConfiguration;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class RestorationWorkspaceController extends Controller
{
    public function index(Request $request, ArchiveProviderConfiguration $providers): View
    {
        $candidateId = $request->integer('candidate') ?: null;
        $jobs = ProcessingJob::query()
            ->with(['mediaItem', 'sourceVersion', 'candidate.candidateVersion'])
            ->when(
                $candidateId !== null,
                fn ($query) => $query->whereHas(
                    'candidate',
                    fn ($candidateQuery) => $candidateQuery->whereKey($candidateId),
                ),
            )
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.restoration', [
            'recipes' => DB::table('processing_recipes')->latest()->limit(20)->get(),
            'jobs' => $jobs,
            'sources' => MediaFileVersion::query()
                ->with('mediaItem')
                ->where('version_type', MediaFileVersionType::Original)
                ->where('generation_status', GenerationStatus::Ready)
                ->where('is_preferred', true)
                ->latest()
                ->limit(30)
                ->get(),
            'events' => ProcessingJobEvent::query()->with('actor')->latest('occurred_at')->limit(15)->get(),
            'provider' => $providers->status(),
            'wasabi' => $providers->status('wasabi'),
            'focusedCandidateId' => $candidateId,
        ]);
    }

    public function queue(Request $request, RestorationWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'source_version_id' => ['required', 'integer', 'exists:media_file_versions,id'],
            'recipe_name' => ['required', 'string', 'max:255'],
            'automation_mode' => ['required', Rule::in(['suggestions', 'candidates'])],
            'auto_rotate' => ['nullable', 'boolean'],
            'deskew' => ['nullable', 'boolean'],
            'crop_target' => ['required', Rule::in(['none', 'photo_edge', 'content'])],
            'exposure' => ['nullable', 'boolean'],
            'color' => ['nullable', 'boolean'],
            'denoise' => ['nullable', 'boolean'],
            'sharpen' => ['nullable', 'boolean'],
            'cleanup' => ['nullable', 'boolean'],
            'perspective' => ['nullable', 'boolean'],
            'damage_repair' => ['nullable', 'boolean'],
            'upscale' => ['nullable', 'boolean'],
            'quality_warnings' => ['nullable', 'boolean'],
        ]);
        $preferences = [
            ...$validated,
            'auto_rotate' => $request->boolean('auto_rotate'),
            'deskew' => $request->boolean('deskew'),
            'exposure' => $request->boolean('exposure'),
            'color' => $request->boolean('color'),
            'denoise' => $request->boolean('denoise'),
            'sharpen' => $request->boolean('sharpen'),
            'cleanup' => $request->boolean('cleanup'),
            'perspective' => $request->boolean('perspective'),
            'damage_repair' => $request->boolean('damage_repair'),
            'upscale' => $request->boolean('upscale'),
            'quality_warnings' => $request->boolean('quality_warnings'),
        ];
        unset($preferences['source_version_id'], $preferences['recipe_name']);

        $source = MediaFileVersion::query()->whereKey($validated['source_version_id'])->firstOrFail();
        $recipe = $workflow->createFromPreferences($validated['recipe_name'], $preferences, $request->user());
        $workflow->queue($source, $recipe, $request->user(), $preferences);

        return back()->with('status', 'Restoration candidate queued. Uploader controls were retained.');
    }

    public function process(
        Request $request,
        ProcessingJob $job,
        GdRestorationCandidateProcessor $processor,
    ): RedirectResponse {
        $processor->process($job, $request->user());

        return back()->with('status', 'A separate restoration candidate is ready for review.');
    }

    public function review(
        Request $request,
        RestorationCandidate $candidate,
        RestorationReviewService $reviews,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['required', 'string', 'max:2000'],
        ]);
        $reviews->decide(
            $candidate,
            $request->user(),
            $validated['decision'],
            $validated['review_note'],
        );

        return back()->with('status', 'Restoration candidate review recorded.');
    }
}
