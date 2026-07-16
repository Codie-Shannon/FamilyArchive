<?php

namespace App\Domain\Duplicates\Services;

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DetectExactDuplicateCandidates
{
    public function detect(IncomingUpload $source): ExactDuplicateDetectionResult
    {
        return DB::transaction(function () use ($source): ExactDuplicateDetectionResult {
            /** @var IncomingUpload $locked */
            $locked = IncomingUpload::query()->lockForUpdate()->findOrFail($source->getKey());
            $sha256 = (string) $locked->sha256;

            if (! $locked->source_file_retained || ! preg_match('/\A[a-f0-9]{64}\z/', $sha256)) {
                throw new InvalidArgumentException('Exact duplicate detection requires a retained source with a normalized SHA-256.');
            }

            $uploadTargets = IncomingUpload::query()
                ->whereKeyNot($locked->getKey())
                ->where('source_file_retained', true)
                ->where('sha256', $sha256)
                ->orderBy('id')->get();

            $versionTargets = MediaFileVersion::query()
                ->where('version_type', MediaFileVersionType::Original->value)
                ->where('sha256', $sha256)
                ->orderBy('id')->get();

            foreach ($uploadTargets as $target) {
                $this->createCandidate($locked, $sha256, $target->getKey(), null);
            }
            foreach ($versionTargets as $target) {
                $this->createCandidate($locked, $sha256, null, $target->getKey());
            }

            $candidates = DuplicateCandidate::query()->where('incoming_upload_id', $locked->getKey())->orderBy('id')->get();
            if ($candidates->isNotEmpty()) {
                $locked->forceFill([
                    'duplicate_status' => DuplicateStatus::PossibleDuplicate,
                    'review_status' => IncomingReviewStatus::PossibleDuplicate,
                ])->save();
            } else {
                $locked->forceFill(['duplicate_status' => DuplicateStatus::NoMatch])->save();
            }

            /** @var list<int> $candidateIds */
            $candidateIds = array_values($candidates->map(static fn (DuplicateCandidate $candidate): int => (int) $candidate->getKey())->all());

            return new ExactDuplicateDetectionResult($candidates->count(), $candidateIds);
        }, 3);
    }

    private function createCandidate(IncomingUpload $source, string $sha256, ?int $uploadId, ?int $versionId): void
    {
        $attributes = [
            'incoming_upload_id' => $source->getKey(),
            'matched_incoming_upload_id' => $uploadId,
            'matched_media_file_version_id' => $versionId,
        ];
        try {
            DuplicateCandidate::query()->firstOrCreate($attributes, [
                'match_method' => DuplicateMatchMethod::ExactSha256,
                'matched_sha256' => $sha256,
                'confidence' => '1.0000',
                'review_state' => DuplicateCandidateReviewState::PendingReview,
                'detected_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! DuplicateCandidate::query()->where($attributes)->exists()) {
                throw $exception;
            }
        }
    }
}
