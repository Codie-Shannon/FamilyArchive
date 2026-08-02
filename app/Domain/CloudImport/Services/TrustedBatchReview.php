<?php

namespace App\Domain\CloudImport\Services;

use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Intake\Actions\ApproveIncomingPhotoForRestoration;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Domain\Processing\Services\RestorationReviewService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class TrustedBatchReview
{
    public const DECISIONS = ['suggested_edit', 'original', 'hold', 'reject'];

    public function __construct(
        private ApproveIncomingPhotoForRestoration $approver,
        private RestorationReviewService $reviews,
        private GeneratePhotoViewingDerivatives $derivatives,
    ) {}

    public function session(string $sessionId, User $actor): object
    {
        $session = DB::table('cloud_import_sessions')
            ->where('session_id', $sessionId)
            ->where('provider', 'manual_export')
            ->first();

        if ($session === null) {
            throw new RuntimeException('The intake batch could not be found.');
        }

        abort_unless(
            $actor->isArchiveAdministrator() || (int) $session->user_id === $actor->id,
            403,
            'You may only review your own trusted-intake batches.',
        );

        return $session;
    }

    /** @return array{prepared:int,attention:int,remaining:int} */
    public function prepare(string $sessionId, User $actor, int $limit = 25): array
    {
        $session = $this->session($sessionId, $actor);
        if (data_get($session, 'state') !== 'complete') {
            throw ValidationException::withMessages(['batch' => 'Finish the upload batch before preparing review previews.']);
        }
        $items = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $this->integer($session, 'id'))
            ->where('state', 'retained')
            ->whereNull('prepared_at')
            ->orderBy('position')
            ->limit(max(1, min($limit, 50)))
            ->get();

        DB::table('cloud_import_sessions')->where('id', $this->integer($session, 'id'))->update([
            'review_state' => 'preparing',
            'updated_at' => now(),
        ]);

        foreach ($items as $item) {
            try {
                $upload = IncomingUpload::query()->whereKey($this->integer($item, 'incoming_upload_id'))->first();
                if (! $upload instanceof IncomingUpload) {
                    throw new RuntimeException('The retained upload is unavailable.');
                }
                $result = $this->approver->handle($upload, $actor);

                if ($result->state === 'duplicate_review') {
                    $this->markPrepared($this->integer($item, 'id'), null, 'exact_duplicate');

                    continue;
                }

                $mediaItem = $result->promotion?->mediaItem;
                if ($mediaItem instanceof MediaItem) {
                    $mediaItem->forceFill([
                        'review_status' => MediaReviewStatus::PendingReview,
                        'approved_by' => null,
                        'approved_at' => null,
                    ])->save();
                }

                $candidate = $result->candidate;
                $attention = $candidate instanceof RestorationCandidate
                    ? $this->candidateAttention($candidate)
                    : null;
                $this->markPrepared($this->integer($item, 'id'), $candidate?->id, $attention);
            } catch (Throwable $exception) {
                report($exception);
                $this->markPrepared($this->integer($item, 'id'), null, 'preparation_failed');
            }
        }

        return $this->refreshSession($this->integer($session, 'id'));
    }

    /** @return array{regenerated:int,failed:int,attention:int} */
    public function regeneratePending(string $sessionId, User $actor, int $limit = 25): array
    {
        $session = $this->session($sessionId, $actor);
        if (data_get($session, 'state') !== 'complete') {
            throw ValidationException::withMessages(['batch' => 'Finish the upload batch before regenerating review previews.']);
        }

        $items = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $this->integer($session, 'id'))
            ->where('state', 'retained')
            ->whereNotNull('prepared_at')
            ->whereNull('review_decision')
            ->orderBy('position')
            ->limit(max(1, min($limit, 50)))
            ->get();

        $regenerated = 0;
        $failed = 0;
        foreach ($items as $item) {
            try {
                $upload = IncomingUpload::query()->whereKey($this->integer($item, 'incoming_upload_id'))->first();
                if (! $upload instanceof IncomingUpload) {
                    throw new RuntimeException('The retained upload is unavailable.');
                }

                $result = $this->approver->regeneratePendingSuggestion($upload, $actor);
                $candidate = $result->candidate;
                $attention = $candidate instanceof RestorationCandidate
                    ? $this->candidateAttention($candidate)
                    : null;
                $this->markPrepared($this->integer($item, 'id'), $candidate?->id, $attention);
                $regenerated++;
            } catch (Throwable $exception) {
                report($exception);
                DB::table('cloud_import_items')->where('id', $this->integer($item, 'id'))->update([
                    'attention_code' => 'regeneration_failed',
                    'updated_at' => now(),
                ]);
                $failed++;
            }
        }

        $counts = $this->refreshSession($this->integer($session, 'id'));

        return ['regenerated' => $regenerated, 'failed' => $failed, 'attention' => $counts['attention']];
    }

    /** @param list<int> $itemIds
     * @return array{reviewed:int,failed:int}
     */
    public function decide(string $sessionId, User $actor, array $itemIds, string $decision): array
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['decision' => 'Choose a supported batch decision.']);
        }
        $session = $this->session($sessionId, $actor);
        $ids = array_values(array_unique(array_map('intval', $itemIds)));
        if ($ids === [] || count($ids) > 50) {
            throw ValidationException::withMessages(['items' => 'Choose between 1 and 50 visible items.']);
        }

        $items = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $this->integer($session, 'id'))
            ->whereIn('id', $ids)
            ->orderBy('position')
            ->get();
        if ($items->count() !== count($ids)) {
            abort(403, 'One or more selected items are outside this batch.');
        }

        $reviewed = 0;
        $failed = 0;
        foreach ($items as $item) {
            try {
                $this->decideItem($item, $actor, $decision);
                $reviewed++;
            } catch (Throwable $exception) {
                report($exception);
                DB::table('cloud_import_items')->where('id', $this->integer($item, 'id'))->update([
                    'attention_code' => 'review_failed',
                    'updated_at' => now(),
                ]);
                $failed++;
            }
        }

        $this->refreshSession($this->integer($session, 'id'), $actor);

        return ['reviewed' => $reviewed, 'failed' => $failed];
    }

    private function decideItem(object $item, User $actor, string $decision): void
    {
        if (data_get($item, 'prepared_at') === null) {
            throw ValidationException::withMessages(['items' => 'Prepare previews before reviewing these items.']);
        }
        if (data_get($item, 'review_decision') !== null) {
            throw ValidationException::withMessages(['items' => 'A selected item has already been reviewed.']);
        }

        $upload = IncomingUpload::query()
            ->with('archivePromotion.mediaItem')
            ->whereKey($this->integer($item, 'incoming_upload_id'))
            ->first();
        if (! $upload instanceof IncomingUpload) {
            throw new RuntimeException('The retained upload is unavailable.');
        }
        $mediaItem = $upload->archivePromotion?->mediaItem;
        $candidateId = data_get($item, 'restoration_candidate_id');
        $candidate = $candidateId === null
            ? null
            : RestorationCandidate::query()
                ->with('sourceVersion.mediaItem')
                ->whereKey((int) $candidateId)
                ->first();
        if (! $mediaItem instanceof MediaItem && $candidate instanceof RestorationCandidate) {
            $mediaItem = $candidate->sourceVersion?->mediaItem;
        }

        if ($decision === 'hold') {
            if ($mediaItem instanceof MediaItem) {
                $this->setMediaState($mediaItem, MediaReviewStatus::NeedsInfo, null);
            }
        } elseif ($decision === 'reject') {
            if ($candidate instanceof RestorationCandidate && $candidate->review_state === 'pending') {
                $this->reviews->decide($candidate, $actor, 'rejected', 'Rejected during trusted batch review.');
            }
            if ($mediaItem instanceof MediaItem) {
                $this->setMediaState($mediaItem, MediaReviewStatus::Rejected, null);
            }
        } else {
            if (! $mediaItem instanceof MediaItem) {
                throw ValidationException::withMessages(['items' => 'This item has no accepted immutable original.']);
            }

            if ($decision === 'suggested_edit') {
                if (! $candidate instanceof RestorationCandidate || $candidate->review_state !== 'pending') {
                    throw ValidationException::withMessages(['items' => 'This item has no available suggested edit.']);
                }
                $this->setMediaState($mediaItem, MediaReviewStatus::Approved, $actor);
                $this->reviews->decide($candidate, $actor, 'approved', 'Suggested edit selected during trusted batch review.');
                $this->derivatives->handle($mediaItem->fresh(), $actor);
            } else {
                $this->setMediaState($mediaItem, MediaReviewStatus::Approved, $actor);
                if ($candidate instanceof RestorationCandidate && $candidate->review_state === 'pending') {
                    $this->reviews->decide($candidate, $actor, 'rejected', 'Original selected during trusted batch review.');
                }
                $this->derivatives->handle($mediaItem->fresh(), $actor);
            }
        }

        DB::table('cloud_import_items')->where('id', $this->integer($item, 'id'))->update([
            'review_decision' => $decision,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contributor_submissions')
            ->where('incoming_upload_id', $upload->id)
            ->update([
                'status' => match ($decision) {
                    'suggested_edit', 'original' => 'accepted',
                    'hold' => 'needs_info',
                    default => 'rejected',
                },
                'reviewed_by' => $actor->id,
                'reviewer_note' => match ($decision) {
                    'suggested_edit' => 'Suggested edit accepted in batch review.',
                    'original' => 'Original accepted in batch review.',
                    'hold' => 'Held for more information in batch review.',
                    default => 'Rejected in batch review.',
                },
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function setMediaState(MediaItem $item, MediaReviewStatus $status, ?User $actor): void
    {
        $item->forceFill([
            'review_status' => $status,
            'approved_by' => $status === MediaReviewStatus::Approved ? $actor?->id : null,
            'approved_at' => $status === MediaReviewStatus::Approved ? now() : null,
        ])->save();
    }

    private function markPrepared(int $itemId, ?int $candidateId, ?string $attention): void
    {
        DB::table('cloud_import_items')->where('id', $itemId)->update([
            'restoration_candidate_id' => $candidateId,
            'attention_code' => $attention,
            'prepared_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function candidateAttention(RestorationCandidate $candidate): ?string
    {
        $analysis = $candidate->analysis;
        $crop = is_array($analysis) ? ($analysis['crop'] ?? null) : null;
        if (is_array($crop)) {
            $applied = (bool) ($crop['applied'] ?? false);
            $qualityGatePassed = (bool) ($crop['quality_gate_passed'] ?? false);
            $requiresReview = (bool) ($crop['requires_review'] ?? false);
            $confidence = (float) ($crop['confidence'] ?? 0.0);
            $areaRatio = (float) ($crop['area_ratio'] ?? 1.0);
            $aspectRatioDelta = (float) ($crop['aspect_ratio_delta'] ?? 0.0);
            $marginBalance = (float) ($crop['margin_balance'] ?? 0.0);

            if (
                $requiresReview
                || ($applied && ! $qualityGatePassed)
                || ($applied && $confidence < 0.72)
                || ($applied && ($areaRatio < 0.45 || $aspectRatioDelta > 0.32 || $marginBalance > 0.28))
            ) {
                return 'crop_check';
            }
        }

        return null;
    }

    private function integer(object $row, string $property): int
    {
        $value = data_get($row, $property);
        if (! is_numeric($value)) {
            throw new RuntimeException("The batch row has no valid {$property}.");
        }

        return (int) $value;
    }

    /** @return array{prepared:int,attention:int,remaining:int} */
    private function refreshSession(int $sessionKey, ?User $actor = null): array
    {
        $counts = DB::table('cloud_import_items')->where('cloud_import_session_id', $sessionKey)
            ->selectRaw('SUM(CASE WHEN prepared_at IS NOT NULL THEN 1 ELSE 0 END) AS prepared_count')
            ->selectRaw('SUM(CASE WHEN attention_code IS NOT NULL THEN 1 ELSE 0 END) AS attention_count')
            ->selectRaw('SUM(CASE WHEN review_decision IS NOT NULL THEN 1 ELSE 0 END) AS reviewed_count')
            ->selectRaw("SUM(CASE WHEN state = 'retained' AND prepared_at IS NULL THEN 1 ELSE 0 END) AS remaining_count")
            ->first();
        $prepared = (int) ($counts->prepared_count ?? 0);
        $attention = (int) ($counts->attention_count ?? 0);
        $reviewed = (int) ($counts->reviewed_count ?? 0);
        $remaining = (int) ($counts->remaining_count ?? 0);
        $retained = (int) DB::table('cloud_import_items')->where('cloud_import_session_id', $sessionKey)->where('state', 'retained')->count();
        $complete = $retained > 0 && $reviewed === $retained;
        $state = $complete ? 'completed' : ($remaining > 0 ? 'preparing' : ($attention > 0 ? 'needs_attention' : 'ready'));

        DB::table('cloud_import_sessions')->where('id', $sessionKey)->update([
            'review_state' => $state,
            'reviewed_count' => $reviewed,
            'attention_count' => $attention,
            'reviewed_by' => $complete ? $actor?->id : null,
            'review_completed_at' => $complete ? now() : null,
            'updated_at' => now(),
        ]);

        return ['prepared' => $prepared, 'attention' => $attention, 'remaining' => $remaining];
    }
}
