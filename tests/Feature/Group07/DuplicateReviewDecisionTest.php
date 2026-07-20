<?php

use App\Domain\Duplicates\Actions\ResolveDuplicateCandidate;
use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Enums\DuplicateReviewDecision;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Duplicates\Models\DuplicateReviewEvent;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function group07Candidate(string $suffix = 'default'): DuplicateCandidate
{
    $hash = hash('sha256', 'group-07-'.$suffix);
    $source = IncomingUpload::factory()->create(['sha256' => $hash]);
    $target = IncomingUpload::factory()->create(['sha256' => $hash]);

    return DuplicateCandidate::query()->create([
        'incoming_upload_id' => $source->id,
        'matched_incoming_upload_id' => $target->id,
        'match_method' => DuplicateMatchMethod::ExactSha256,
        'matched_sha256' => $hash,
        'confidence' => '1.0000',
        'review_state' => DuplicateCandidateReviewState::PendingReview,
        'detected_at' => now(),
    ]);
}

it('defines the exact decision contract', function () {
    expect(array_map(fn ($case) => $case->value, DuplicateReviewDecision::cases()))->toBe([
        'confirmed_duplicate', 'alternate_source', 'related_but_distinct', 'not_duplicate',
    ]);
});

it('records every decision transactionally and updates the source status', function (DuplicateReviewDecision $decision) {
    $candidate = group07Candidate($decision->value);
    $owner = User::factory()->create(['role' => 'owner']);
    $reason = $decision->requiresInitialNote() ? 'Required fictional review note.' : null;

    $resolved = app(ResolveDuplicateCandidate::class)->handle($candidate, $decision, $reason, $owner, ['route' => 'admin.duplicate-candidates.resolve', 'method' => 'POST']);

    expect($resolved->review_state)->toBe(DuplicateCandidateReviewState::Resolved)
        ->and($resolved->resolution)->toBe($decision)
        ->and($resolved->incomingUpload->fresh()->duplicate_status)->toBe(DuplicateStatus::from($decision->value))
        ->and(DuplicateReviewEvent::count())->toBe(1)
        ->and(DuplicateReviewEvent::first()->request_context)->toBe(['route' => 'admin.duplicate-candidates.resolve', 'method' => 'POST']);
})->with(DuplicateReviewDecision::cases());

it('requires notes for alternate source related distinct and all corrections', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    foreach ([DuplicateReviewDecision::AlternateSource, DuplicateReviewDecision::RelatedButDistinct] as $decision) {
        expect(fn () => app(ResolveDuplicateCandidate::class)->handle(group07Candidate($decision->value), $decision, null, $owner))->toThrow(ValidationException::class);
    }
    $candidate = group07Candidate('correction-note');
    app(ResolveDuplicateCandidate::class)->handle($candidate, DuplicateReviewDecision::ConfirmedDuplicate, null, $owner);
    expect(fn () => app(ResolveDuplicateCandidate::class)->handle($candidate, DuplicateReviewDecision::NotDuplicate, null, $owner))->toThrow(ValidationException::class);
});

it('is idempotent for a repeated identical decision', function () {
    $candidate = group07Candidate('idempotent');
    $owner = User::factory()->create(['role' => 'owner']);
    $action = app(ResolveDuplicateCandidate::class);
    $action->handle($candidate, DuplicateReviewDecision::ConfirmedDuplicate, null, $owner);
    $action->handle($candidate, DuplicateReviewDecision::ConfirmedDuplicate, 'ignored repeat', $owner);
    expect(DuplicateReviewEvent::count())->toBe(1);
});

it('appends immutable correction history', function () {
    $candidate = group07Candidate('correction');
    $owner = User::factory()->create(['role' => 'owner']);
    $action = app(ResolveDuplicateCandidate::class);
    $action->handle($candidate, DuplicateReviewDecision::ConfirmedDuplicate, null, $owner);
    $action->handle($candidate, DuplicateReviewDecision::RelatedButDistinct, 'Correction after comparing the fictional records.', $owner);
    $events = DuplicateReviewEvent::query()->orderBy('id')->get();
    expect($events)->toHaveCount(2)
        ->and($events[0]->previous_decision)->toBeNull()
        ->and($events[1]->previous_decision)->toBe(DuplicateReviewDecision::ConfirmedDuplicate)
        ->and($events[1]->new_decision)->toBe(DuplicateReviewDecision::RelatedButDistinct);
    expect(fn () => $events[0]->update(['reason' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => $events[0]->delete())->toThrow(LogicException::class);
});

it('rolls back candidate source and event on injected failure', function () {
    $candidate = group07Candidate('rollback');
    $owner = User::factory()->create(['role' => 'owner']);
    expect(fn () => app(ResolveDuplicateCandidate::class)->handle($candidate, DuplicateReviewDecision::ConfirmedDuplicate, null, $owner, [], fn () => throw new RuntimeException('forced group07 failure')))->toThrow(RuntimeException::class);
    expect($candidate->fresh()->review_state)->toBe(DuplicateCandidateReviewState::PendingReview)
        ->and($candidate->fresh()->resolution)->toBeNull()
        ->and($candidate->incomingUpload->fresh()->duplicate_status)->toBe(DuplicateStatus::NotChecked)
        ->and(DuplicateReviewEvent::count())->toBe(0);
});

it('does not mutate private storage', function () {
    $disks = ['archive_originals', 'archive_derivatives', 'archive_quarantine', 'archive_manifests'];
    foreach ($disks as $disk) { Storage::fake($disk); Storage::disk($disk)->put('proof/unchanged.txt', 'unchanged'); }
    $before = collect($disks)->mapWithKeys(fn ($disk) => [$disk => hash('sha256', Storage::disk($disk)->get('proof/unchanged.txt'))])->all();
    $candidate = group07Candidate('storage');
    app(ResolveDuplicateCandidate::class)->handle($candidate, DuplicateReviewDecision::ConfirmedDuplicate, null, User::factory()->create(['role' => 'owner']));
    $after = collect($disks)->mapWithKeys(fn ($disk) => [$disk => hash('sha256', Storage::disk($disk)->get('proof/unchanged.txt'))])->all();
    expect($after)->toBe($before);
});
