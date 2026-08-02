<?php

namespace App\Domain\Intake\Actions;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Archive\Actions\PromoteIncomingPhoto;
use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Duplicates\Services\DetectExactDuplicateCandidates;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Intake\ValueObjects\IncomingPhotoAutomationResult;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Domain\Processing\Services\GdRestorationCandidateProcessor;
use App\Domain\Processing\Services\RestorationWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApproveIncomingPhotoForRestoration
{
    public function __construct(
        private DetectExactDuplicateCandidates $duplicates,
        private PromoteIncomingPhoto $promoter,
        private RestorationWorkflow $workflow,
        private GdRestorationCandidateProcessor $processor,
    ) {}

    public function handle(IncomingUpload $upload, User $actor): IncomingPhotoAutomationResult
    {
        if (! $actor->canManageTrustedIntake() || $actor->email_verified_at === null) {
            abort(403, 'A verified trusted-intake account is required.');
        }

        $promotion = ArchivePromotion::query()
            ->where('incoming_upload_id', $upload->id)
            ->with(['originalVersion', 'mediaItem'])
            ->first();

        if (! $promotion instanceof ArchivePromotion) {
            $duplicateResult = $this->duplicates->detect($upload);
            if ($duplicateResult->candidateCount > 0) {
                $this->markSubmission($upload, $actor, 'possible_duplicate', 'Exact-match review is required before archive acceptance.');

                return new IncomingPhotoAutomationResult(
                    state: 'duplicate_review',
                    duplicateCandidateIds: $duplicateResult->candidateIds,
                );
            }

            $promotion = $this->promoter->handle($upload->fresh(), $actor);
            $this->markSubmission($upload, $actor, 'accepted', 'Accepted after exact duplicate and retained-source integrity checks.');
        }

        $source = $promotion->originalVersion;
        if ($source === null) {
            throw ValidationException::withMessages([
                'upload' => 'The accepted upload has no immutable original version.',
            ]);
        }

        $existing = ProcessingJob::query()
            ->with('candidate.candidateVersion')
            ->where('source_version_id', $source->id)
            ->whereIn('state', ['queued', 'running', 'candidate_ready', 'approved'])
            ->latest('id')
            ->first();

        if ($existing instanceof ProcessingJob) {
            $candidate = $existing->candidate;
            if ($candidate instanceof RestorationCandidate) {
                return new IncomingPhotoAutomationResult('candidate_ready', promotion: $promotion, job: $existing, candidate: $candidate);
            }

            if ($existing->state === 'queued') {
                $candidate = $this->processor->process($existing, $actor);

                return new IncomingPhotoAutomationResult('candidate_ready', promotion: $promotion, job: $existing->fresh(), candidate: $candidate);
            }
        }

        $submission = ContributorSubmission::query()
            ->where('incoming_upload_id', $upload->id)
            ->first();
        $preferences = is_array($submission?->automation_preferences)
            ? $submission->automation_preferences
            : [];
        $preferences = $this->workflow->normalizePreferences($preferences);

        if ($preferences['automation_mode'] !== 'candidates') {
            return new IncomingPhotoAutomationResult('original_accepted', promotion: $promotion);
        }

        $recipeId = $this->workflow->createFromPreferences(
            'Uploader-controlled photo restoration',
            $preferences,
            $actor,
        );
        $jobId = $this->workflow->queue($source, $recipeId, $actor, $preferences);
        $job = ProcessingJob::query()->where('job_id', $jobId)->firstOrFail();
        $candidate = $this->processor->process($job, $actor);

        return new IncomingPhotoAutomationResult('candidate_ready', promotion: $promotion, job: $job->fresh(), candidate: $candidate);
    }

    public function regeneratePendingSuggestion(IncomingUpload $upload, User $actor): IncomingPhotoAutomationResult
    {
        if (! $actor->canManageTrustedIntake() || $actor->email_verified_at === null) {
            abort(403, 'A verified trusted-intake account is required.');
        }

        $promotion = ArchivePromotion::query()
            ->where('incoming_upload_id', $upload->id)
            ->with(['originalVersion', 'mediaItem'])
            ->first();
        if (! $promotion instanceof ArchivePromotion || $promotion->originalVersion === null) {
            throw ValidationException::withMessages([
                'upload' => 'A retained immutable original is required before regenerating its suggestion.',
            ]);
        }

        $source = $promotion->originalVersion;
        $submission = ContributorSubmission::query()
            ->where('incoming_upload_id', $upload->id)
            ->first();
        $preferences = is_array($submission?->automation_preferences)
            ? $submission->automation_preferences
            : [];
        $preferences = $this->workflow->normalizePreferences($preferences);
        if ($preferences['automation_mode'] !== 'candidates') {
            return new IncomingPhotoAutomationResult('original_accepted', promotion: $promotion);
        }

        $recipeId = $this->workflow->createFromPreferences(
            'Uploader-controlled photo restoration',
            $preferences,
            $actor,
        );
        $jobId = $this->workflow->queue($source, $recipeId, $actor, $preferences);
        $job = ProcessingJob::query()->where('job_id', $jobId)->firstOrFail();
        $candidate = $this->processor->process($job, $actor);

        DB::transaction(function () use ($source, $candidate, $actor): void {
            $superseded = RestorationCandidate::query()
                ->where('source_version_id', $source->id)
                ->where('review_state', 'pending')
                ->whereKeyNot($candidate->id)
                ->lockForUpdate()
                ->get();

            foreach ($superseded as $oldCandidate) {
                $oldCandidate->forceFill([
                    'review_state' => 'rejected',
                    'reviewed_by' => $actor->id,
                    'review_note' => 'Superseded by regenerated batch-review suggestion.',
                    'reviewed_at' => now(),
                ])->save();
                $oldCandidate->job()->update([
                    'state' => 'rejected',
                    'completed_at' => now(),
                ]);
            }
        });

        return new IncomingPhotoAutomationResult('candidate_ready', promotion: $promotion, job: $job->fresh(), candidate: $candidate);
    }

    private function markSubmission(IncomingUpload $upload, User $actor, string $status, string $note): void
    {
        ContributorSubmission::query()
            ->where('incoming_upload_id', $upload->id)
            ->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewer_note' => $note,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
