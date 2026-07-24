<?php

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;

function group07AccessCandidate(): DuplicateCandidate
{
    $hash = hash('sha256', 'group-07-access');
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

it('allows verified owners and denies guests and non owners', function () {
    $candidate = group07AccessCandidate();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);
    $this->get(route('admin.duplicate-candidates.index'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.duplicate-candidates.index'))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.duplicate-candidates.index'))->assertOk()->assertSee('Pending manual review')->assertSee('Resolved decisions');
    $this->actingAs($owner)->post(route('admin.duplicate-candidates.resolve', $candidate), ['decision' => 'confirmed_duplicate'])->assertRedirect();
    $this->actingAs($viewer)->post(route('admin.duplicate-candidates.resolve', $candidate), ['decision' => 'not_duplicate', 'reason' => 'No.'])->assertForbidden();
});

it('has no delete download merge replacement or promotion route', function () {
    $candidate = group07AccessCandidate();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    foreach (['delete', 'download', 'merge', 'replace', 'promote'] as $action) {
        $this->actingAs($owner)->post('/admin/duplicate-candidates/'.$candidate->id.'/'.$action)->assertNotFound();
    }
    $this->actingAs($owner)->delete('/admin/duplicate-candidates/'.$candidate->id)->assertMethodNotAllowed();
});
