<?php

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Intake\Models\IncomingUpload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('installs the forward-only duplicate candidate schema', function () {
    expect(Schema::hasColumns('duplicate_candidates', [
        'incoming_upload_id', 'matched_incoming_upload_id', 'matched_media_file_version_id', 'match_method',
        'matched_sha256', 'confidence', 'review_state', 'detected_at', 'reviewed_by', 'reviewed_at', 'resolution',
    ]))->toBeTrue();
});

it('enforces exactly one non-self target and restrictive deletion', function () {
    $source = IncomingUpload::factory()->create();
    $target = IncomingUpload::factory()->create();
    $base = [
        'incoming_upload_id' => $source->id,
        'match_method' => DuplicateMatchMethod::ExactSha256,
        'matched_sha256' => $source->sha256,
        'confidence' => '1.0000',
        'review_state' => DuplicateCandidateReviewState::PendingReview,
        'detected_at' => now(),
    ];
    expect(fn () => DuplicateCandidate::create($base))->toThrow(QueryException::class);
    expect(fn () => DuplicateCandidate::create($base + ['matched_incoming_upload_id' => $source->id]))->toThrow(QueryException::class);
    $candidate = DuplicateCandidate::create($base + ['matched_incoming_upload_id' => $target->id]);
    expect(fn () => $target->delete())->toThrow(QueryException::class);
    expect($candidate->exists)->toBeTrue();
});
