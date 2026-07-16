<?php

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;

function group06Candidate(): DuplicateCandidate
{
    $hash = hash('sha256', 'group-06-access');
    $source = IncomingUpload::factory()->create(['sha256' => $hash]);
    $target = IncomingUpload::factory()->create(['sha256' => $hash]);
    return DuplicateCandidate::create([
        'incoming_upload_id' => $source->id,
        'matched_incoming_upload_id' => $target->id,
        'match_method' => DuplicateMatchMethod::ExactSha256,
        'matched_sha256' => $hash,
        'confidence' => '1.0000',
        'review_state' => DuplicateCandidateReviewState::PendingReview,
        'detected_at' => now(),
    ]);
}

it('allows only owners to read queue and detail', function () {
    $candidate = group06Candidate();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get('/admin/duplicate-candidates')->assertRedirect('/login');
    $this->actingAs($viewer)->get('/admin/duplicate-candidates')->assertForbidden();
    $this->actingAs($owner)->get('/admin/duplicate-candidates')->assertOk()->assertSee('Possible exact duplicate');
    $this->actingAs($owner)->get('/admin/duplicate-candidates/'.$candidate->id)->assertOk()->assertSee('exact_sha256')->assertSee('No bytes changed');
});

it('exposes no mutation or resolution routes', function () {
    $candidate = group06Candidate();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        $this->actingAs($owner)->{$method}('/admin/duplicate-candidates/'.$candidate->id)->assertMethodNotAllowed();
    }
});
