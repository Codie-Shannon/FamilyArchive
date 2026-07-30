<?php

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\OriginalAccessGrant;
use App\Domain\Access\Models\UploadSession;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function sg12User(array $attributes = []): User
{
    return User::factory()->create([
        'role' => 'viewer',
        'account_state' => 'approved',
        'email_verified_at' => now(),
        ...$attributes,
    ]);
}

function sg12ApprovedPhoto(User $owner, string $archiveId, MediaVisibility $visibility, ?int $branchId = null): MediaItem
{
    return MediaItem::factory()->create([
        'archive_id' => $archiveId,
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
        'approved_at' => now(),
        'review_status' => MediaReviewStatus::Approved,
        'visibility' => $visibility,
        'family_branch_id' => $branchId,
    ]);
}

it('accepts a one-use invitation into verified-email and owner-approval gates', function (): void {
    Notification::fake();
    $owner = sg12User(['role' => 'owner']);
    $token = Str::random(64);
    $invitation = UserInvitation::query()->create([
        'invitation_id' => (string) Str::uuid(),
        'email' => 'invited-member@example.test',
        'name' => 'Invited Member',
        'role' => 'contributor',
        'token_hash' => hash('sha256', $token),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDay(),
    ]);

    $this->post(route('invitation.accept', [$invitation->invitation_id, $token]), [
        'password' => 'A-safe-test-password-123!',
        'password_confirmation' => 'A-safe-test-password-123!',
    ])->assertRedirect(route('verification.notice'));

    $member = User::query()->where('email', 'invited-member@example.test')->firstOrFail();
    expect($member->account_state)->toBe('pending')
        ->and($member->email_verified_at)->toBeNull()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and(AccountAccessEvent::query()->where('user_id', $member->id)->where('event_type', 'invitation_accepted')->exists())->toBeTrue();
    Notification::assertSentTo($member, VerifyEmail::class);

    auth()->logout();
    $this->post(route('invitation.accept', [$invitation->invitation_id, $token]), [
        'password' => 'A-safe-test-password-123!',
        'password_confirmation' => 'A-safe-test-password-123!',
    ])->assertGone();
});

it('requires both verification and owner approval for archive access', function (): void {
    $unverified = sg12User(['email_verified_at' => null]);
    $pending = sg12User(['account_state' => 'pending']);

    $this->actingAs($unverified)->get(route('archive.index'))->assertRedirect(route('verification.notice'));
    $this->actingAs($pending)->get(route('archive.index'))->assertForbidden();
});

it('filters approved archive records by role visibility and family branch', function (): void {
    $owner = sg12User(['role' => 'owner']);
    $branch = FamilyBranch::factory()->create();
    $otherBranch = FamilyBranch::factory()->create();
    $member = sg12User(['family_branch_id' => $branch->id]);

    sg12ApprovedPhoto($owner, 'SG12-FAMILY', MediaVisibility::FamilyVisible);
    sg12ApprovedPhoto($owner, 'SG12-BRANCH', MediaVisibility::BranchVisible, $branch->id);
    sg12ApprovedPhoto($owner, 'SG12-OTHER', MediaVisibility::BranchVisible, $otherBranch->id);
    sg12ApprovedPhoto($owner, 'SG12-PRIVATE', MediaVisibility::PrivateArchive);

    $this->actingAs($member)->get(route('archive.index'))
        ->assertOk()
        ->assertSee('SG12-FAMILY')
        ->assertSee('SG12-BRANCH')
        ->assertDontSee('SG12-OTHER')
        ->assertDontSee('SG12-PRIVATE');
    $this->actingAs($owner)->get(route('archive.index'))
        ->assertSee('SG12-OTHER')
        ->assertSee('SG12-PRIVATE');
});

it('serves an integrity-checked original only while a grant is active', function (): void {
    Storage::fake('archive_originals');
    $owner = sg12User(['role' => 'owner']);
    $member = sg12User();
    $item = sg12ApprovedPhoto($owner, 'SG12-GRANT', MediaVisibility::FamilyVisible);
    $bytes = 'fictional-original-bytes';
    $path = 'sg12/fictional-original.jpg';
    Storage::disk('archive_originals')->put($path, $bytes);
    $original = MediaFileVersion::factory()->create([
        'media_item_id' => $item->id,
        'version_type' => MediaFileVersionType::Original,
        'storage_disk' => 'archive_originals',
        'storage_path' => $path,
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'file_size_bytes' => strlen($bytes),
        'sha256' => hash('sha256', $bytes),
        'generation_status' => GenerationStatus::Ready,
        'is_preferred' => true,
    ]);

    $this->actingAs($member)->get(route('archive.originals.show', $original))->assertNotFound();
    $grant = OriginalAccessGrant::query()->create([
        'user_id' => $member->id,
        'media_item_id' => $item->id,
        'granted_by' => $owner->id,
        'reason' => 'Fictional research access.',
        'effective_at' => now()->subMinute(),
    ]);
    $this->actingAs($member)->get(route('archive.originals.show', $original))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertContent($bytes);
    $grant->forceFill(['revoked_at' => now(), 'revocation_reason' => 'Research complete.'])->save();
    $this->actingAs($member)->get(route('archive.originals.show', $original))->assertNotFound();
});

it('prevents the last approved owner from being locked out and records valid changes', function (): void {
    $owner = sg12User(['role' => 'owner']);
    $member = sg12User(['account_state' => 'pending']);

    $this->actingAs($owner)->patch(route('admin.access.users.update', $owner), [
        'role' => 'viewer',
        'account_state' => 'approved',
        'family_branch_id' => null,
        'family_connection' => null,
        'reason' => 'Unsafe test demotion.',
    ])->assertUnprocessable();

    $this->actingAs($owner)->patch(route('admin.access.users.update', $member), [
        'role' => 'contributor',
        'account_state' => 'approved',
        'family_branch_id' => null,
        'family_connection' => 'Fictional cousin',
        'reason' => 'Identity confirmed by owner.',
    ])->assertRedirect();

    expect($member->fresh()->account_state)->toBe('approved')
        ->and($member->fresh()->role)->toBe('contributor')
        ->and(AccountAccessEvent::query()->where('user_id', $member->id)->where('event_type', 'account_access_updated')->count())->toBe(1);
});

it('creates a resumable multi-file contributor session and retains sources in quarantine', function (): void {
    Storage::fake('archive_quarantine');
    $contributor = sg12User(['role' => 'contributor']);

    $this->actingAs($contributor)->post(route('contributor.sessions.start'), [
        'title' => 'Fictional album batch',
        'source_context' => 'Synthetic family album used only for automated tests.',
        'expected_files' => 2,
        'automation_mode' => 'suggestions',
        'auto_rotate' => 1,
        'deskew' => 1,
        'perspective' => 1,
        'crop_target' => 'photo_edge',
        'quality_warnings' => 1,
    ])->assertRedirect();
    $session = UploadSession::query()->sole();

    $this->actingAs($contributor)->post(route('contributor.sessions.upload', $session), [
        'photos' => [UploadedFile::fake()->image('fictional-one.jpg', 800, 600)],
    ])->assertRedirect();
    expect($session->fresh()->status)->toBe('paused')
        ->and($session->fresh()->received_files)->toBe(1);

    $this->actingAs($contributor)->post(route('contributor.sessions.upload', $session), [
        'photos' => [UploadedFile::fake()->image('fictional-two.jpg', 800, 600)],
    ])->assertRedirect();
    expect($session->fresh()->status)->toBe('complete')
        ->and($session->fresh()->received_files)->toBe(2)
        ->and(ContributorSubmission::query()->count())->toBe(2)
        ->and(ContributorSubmission::query()->whereHas('incomingUpload', fn ($query) => $query->where('source_file_retained', true))->count())->toBe(2)
        ->and($session->fresh()->automation_preferences['auto_rotate'])->toBeTrue();
});
