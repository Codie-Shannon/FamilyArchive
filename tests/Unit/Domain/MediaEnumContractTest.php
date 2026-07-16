<?php

use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;

it('keeps every Group 02 enum contract exact', function (string $enumClass, array $expectedValues): void {
    expect(array_column($enumClass::cases(), 'value'))->toBe($expectedValues);
})->with([
    'media type' => [MediaType::class, ['photo', 'video', 'document', 'audio', 'other']],
    'media review status' => [MediaReviewStatus::class, ['pending_review', 'needs_info', 'approved', 'hidden', 'rejected']],
    'media visibility' => [MediaVisibility::class, ['admin_only', 'private_archive', 'family_visible', 'branch_visible', 'hidden_sensitive', 'public_highlight_candidate', 'public_highlight_approved']],
    'sensitivity status' => [SensitivityStatus::class, ['not_flagged', 'review_required', 'sensitive', 'restricted']],
    'date confidence' => [DateConfidence::class, ['exact', 'estimated', 'decade_only', 'unknown']],
    'incoming processing status' => [IncomingProcessingStatus::class, ['pending', 'validating', 'processing', 'processed', 'failed', 'quarantined']],
    'incoming review status' => [IncomingReviewStatus::class, ['pending_review', 'possible_duplicate', 'needs_info', 'approved', 'rejected']],
    'duplicate status' => [DuplicateStatus::class, ['not_checked', 'no_match', 'possible_duplicate', 'confirmed_duplicate', 'alternate_source', 'related_but_distinct', 'not_duplicate']],
    'media file version type' => [MediaFileVersionType::class, ['original', 'edited_full', 'web_display', 'thumbnail', 'video_stream', 'video_preview', 'document_preview']],
    'generation status' => [GenerationStatus::class, ['pending', 'processing', 'ready', 'failed', 'not_required']],
]);
