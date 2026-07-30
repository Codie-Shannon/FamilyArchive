<?php

namespace App\Domain\Processing\Services;

use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\ProcessingJobEvent;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class RestorationReviewService
{
    public function decide(RestorationCandidate $candidate, User $reviewer, string $decision, string $note): void
    {
        if (! $reviewer->isArchiveAdministrator()) {
            abort(403, 'Archive administrator access is required.');
        }
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
        }
        if (trim($note) === '') {
            throw ValidationException::withMessages(['review_note' => 'A review note is required.']);
        }

        DB::transaction(function () use ($candidate, $reviewer, $decision, $note): void {
            $locked = RestorationCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($locked->review_state !== 'pending') {
                throw ValidationException::withMessages(['candidate' => 'This candidate has already been reviewed.']);
            }

            $source = MediaFileVersion::query()->lockForUpdate()->findOrFail($locked->source_version_id);
            $version = MediaFileVersion::query()->lockForUpdate()->findOrFail($locked->candidate_version_id);
            $this->verifyObject($source);
            $this->verifyObject($version);

            if ($decision === 'approved') {
                MediaFileVersion::query()
                    ->where('media_item_id', $version->media_item_id)
                    ->where('version_type', $version->version_type)
                    ->where('id', '!=', $version->id)
                    ->update(['is_preferred' => false]);
                $version->forceFill(['is_preferred' => true])->save();
            }

            $locked->forceFill([
                'review_state' => $decision,
                'reviewed_by' => $reviewer->id,
                'review_note' => trim($note),
                'reviewed_at' => now(),
            ])->save();

            $job = ProcessingJob::query()->lockForUpdate()->findOrFail($locked->processing_job_id);
            $job->forceFill(['state' => $decision])->save();
            ProcessingJobEvent::query()->create([
                'processing_job_id' => $job->id,
                'actor_id' => $reviewer->id,
                'event' => 'candidate_'.$decision,
                'safe_context' => [
                    'candidate_id' => $locked->candidate_id,
                    'original_retained' => true,
                ],
                'occurred_at' => now(),
            ]);

            $this->verifyObject($source);
        }, 5);
    }

    private function verifyObject(MediaFileVersion $version): void
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($version->storage_disk);
        if (! $disk->exists($version->storage_path)) {
            throw new DerivativeGenerationException('A reviewed restoration object is missing.');
        }
        $bytes = $disk->get($version->storage_path);
        if (
            strlen($bytes) !== $version->file_size_bytes
            || ! hash_equals(strtolower($version->sha256), hash('sha256', $bytes))
        ) {
            throw new DerivativeGenerationException('A reviewed restoration object failed integrity verification.');
        }
    }
}
