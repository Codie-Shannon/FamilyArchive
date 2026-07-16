<?php

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Duplicates\Services\DetectExactDuplicateCandidates;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function inventory(string $disk): array
{
    $files = Storage::disk($disk)->allFiles();
    sort($files);
    return $files;
}

it('defines the exact match and pending review enum contracts', function () {
    expect(DuplicateMatchMethod::ExactSha256->value)->toBe('exact_sha256')
        ->and(DuplicateCandidateReviewState::PendingReview->value)->toBe('pending_review');
});

it('creates one exact retained-upload candidate and is idempotent', function () {
    $hash = hash('sha256', 'group-06-exact-upload');
    $source = IncomingUpload::factory()->create(['sha256' => $hash]);
    $target = IncomingUpload::factory()->create(['sha256' => $hash]);

    $first = app(DetectExactDuplicateCandidates::class)->detect($source);
    $second = app(DetectExactDuplicateCandidates::class)->detect($source->fresh());

    expect($first->candidateCount)->toBe(1)->and($second->candidateCount)->toBe(1);
    $candidate = DuplicateCandidate::firstOrFail();
    expect($candidate->matched_incoming_upload_id)->toBe($target->id)
        ->and($candidate->matched_media_file_version_id)->toBeNull()
        ->and($candidate->matched_sha256)->toBe($hash)
        ->and($candidate->confidence)->toBe('1.0000');
    expect($source->fresh()->duplicate_status)->toBe(DuplicateStatus::PossibleDuplicate)
        ->and($source->fresh()->review_status)->toBe(IncomingReviewStatus::PossibleDuplicate)
        ->and(DuplicateCandidate::count())->toBe(1);
});

it('creates candidates for every distinct eligible target including archived originals only', function () {
    $hash = hash('sha256', 'group-06-multiple-targets');
    $source = IncomingUpload::factory()->create(['sha256' => $hash]);
    IncomingUpload::factory()->count(2)->create(['sha256' => $hash]);
    MediaFileVersion::factory()->create(['sha256' => $hash, 'version_type' => MediaFileVersionType::Original]);
    MediaFileVersion::factory()->create(['sha256' => $hash, 'version_type' => MediaFileVersionType::Thumbnail]);

    $result = app(DetectExactDuplicateCandidates::class)->detect($source);

    expect($result->candidateCount)->toBe(3)
        ->and(DuplicateCandidate::whereNotNull('matched_incoming_upload_id')->count())->toBe(2)
        ->and(DuplicateCandidate::whereNotNull('matched_media_file_version_id')->count())->toBe(1);
});

it('sets no match without changing pending review', function () {
    $source = IncomingUpload::factory()->create();
    $result = app(DetectExactDuplicateCandidates::class)->detect($source);

    expect($result->candidateCount)->toBe(0)
        ->and(DuplicateCandidate::count())->toBe(0)
        ->and($source->fresh()->duplicate_status)->toBe(DuplicateStatus::NoMatch)
        ->and($source->fresh()->review_status)->toBe(IncomingReviewStatus::PendingReview);
});

it('excludes self and fails closed for unretained or malformed normalized hashes', function (array $state) {
    $source = IncomingUpload::factory()->create($state);
    expect(fn () => app(DetectExactDuplicateCandidates::class)->detect($source))->toThrow(InvalidArgumentException::class);
    expect(DuplicateCandidate::count())->toBe(0)->and($source->fresh()->duplicate_status)->toBe(DuplicateStatus::NotChecked);
})->with([
    'not retained' => [['source_file_retained' => false]],
    'malformed' => [['sha256' => str_repeat('z', 64)]],
    'uppercase not normalized' => [['sha256' => strtoupper(hash('sha256', 'uppercase'))]],
]);

it('rolls candidate and status changes back on database failure', function () {
    $hash = hash('sha256', 'group-06-rollback');
    $source = IncomingUpload::factory()->create(['sha256' => $hash]);
    IncomingUpload::factory()->create(['sha256' => $hash]);
    DB::unprepared("CREATE TRIGGER group06_forced_failure BEFORE INSERT ON duplicate_candidates BEGIN SELECT RAISE(ABORT, 'forced group06 failure'); END;");

    expect(fn () => app(DetectExactDuplicateCandidates::class)->detect($source))->toThrow(QueryException::class, 'forced group06 failure');
    expect(DuplicateCandidate::count())->toBe(0)
        ->and($source->fresh()->duplicate_status)->toBe(DuplicateStatus::NotChecked)
        ->and($source->fresh()->review_status)->toBe(IncomingReviewStatus::PendingReview);
});

it('does not mutate any private archive disk inventory', function () {
    $disks = ['archive_originals', 'archive_derivatives', 'archive_quarantine', 'archive_manifests'];
    foreach ($disks as $disk) { Storage::fake($disk); Storage::disk($disk)->put('proof/unchanged.txt', 'unchanged'); }
    $before = collect($disks)->mapWithKeys(fn ($disk) => [$disk => inventory($disk)])->all();
    $hash = hash('sha256', 'group-06-storage-neutral');
    $source = IncomingUpload::factory()->create(['sha256' => $hash]);
    IncomingUpload::factory()->create(['sha256' => $hash]);

    app(DetectExactDuplicateCandidates::class)->detect($source);
    app(DetectExactDuplicateCandidates::class)->detect($source->fresh());

    $after = collect($disks)->mapWithKeys(fn ($disk) => [$disk => inventory($disk)])->all();
    expect($after)->toBe($before);
});
