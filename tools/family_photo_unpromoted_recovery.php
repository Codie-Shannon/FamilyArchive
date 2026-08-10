<?php

use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Intake\Actions\ApproveIncomingPhotoForRestoration;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $ownerEmail = (string) ($input['owner_email'] ?? '');
    $itemId = max(0, (int) ($input['item_id'] ?? 0));

    $session = DB::table('cloud_import_sessions')->where('session_id', $sessionId)->firstOrFail();
    $actor = User::query()->where('email', $ownerEmail)->firstOrFail();
    $item = DB::table('cloud_import_items')
        ->where('cloud_import_session_id', (int) $session->id)
        ->where('id', $itemId)
        ->firstOrFail();

    if ((string) $item->state !== 'retained') {
        throw new RuntimeException('Only retained import items may be recovered.');
    }
    if ((string) $item->review_decision !== 'hold' || (string) $item->attention_code !== 'preparation_failed') {
        throw new RuntimeException('The item is not an unresolved preparation-failed hold.');
    }

    $upload = IncomingUpload::query()->findOrFail((int) $item->incoming_upload_id);
    if (! $upload->source_file_retained || ! is_string($upload->incoming_path) || $upload->incoming_path === '') {
        throw new RuntimeException('The retained source is unavailable.');
    }
    if (! preg_match('/\A[a-f0-9]{64}\z/', (string) $upload->sha256) || (int) $upload->file_size_bytes < 1) {
        throw new RuntimeException('The retained source lacks exact integrity facts.');
    }

    $existing = ArchivePromotion::query()->where('incoming_upload_id', $upload->id)->first();
    if (! $existing instanceof ArchivePromotion) {
        $matches = DB::table('media_file_versions')
            ->where('version_type', 'original')
            ->where('sha256', (string) $upload->sha256)
            ->count();
        if ($matches > 0) {
            throw new RuntimeException('The source now has an exact canonical original and must be reconciled as a duplicate.');
        }

        try {
            app(ApproveIncomingPhotoForRestoration::class)->handle($upload, $actor);
        } catch (Throwable $exception) {
            $existing = ArchivePromotion::query()->where('incoming_upload_id', $upload->id)->first();
            if (! $existing instanceof ArchivePromotion) {
                throw $exception;
            }
        }
    }

    $promotion = ArchivePromotion::query()
        ->with(['mediaItem', 'originalVersion'])
        ->where('incoming_upload_id', $upload->id)
        ->firstOrFail();
    if ($promotion->mediaItem === null || $promotion->originalVersion === null) {
        throw new RuntimeException('The recovered promotion is incomplete.');
    }
    if (! hash_equals((string) $upload->sha256, (string) $promotion->originalVersion->sha256)
        || (int) $upload->file_size_bytes !== (int) $promotion->originalVersion->file_size_bytes) {
        throw new RuntimeException('The recovered immutable original does not match the retained source identity.');
    }

    $promotion->mediaItem->forceFill([
        'review_status' => MediaReviewStatus::PendingReview,
        'approved_by' => null,
        'approved_at' => null,
    ])->save();

    return [
        'item_id' => $itemId,
        'incoming_upload_id' => (int) $upload->id,
        'media_item_id' => (int) $promotion->media_item_id,
        'original_version_id' => (int) $promotion->original_media_file_version_id,
        'sha256' => (string) $promotion->originalVersion->sha256,
        'bytes' => (int) $promotion->originalVersion->file_size_bytes,
        'review_status' => (string) $promotion->mediaItem->review_status->value,
        'status' => 'promoted_pending_review',
    ];
};
