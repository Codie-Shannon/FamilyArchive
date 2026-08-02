<?php

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\UserInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function sg29Operator(string $role = 'owner'): User
{
    return User::factory()->create(['role' => $role, 'account_state' => 'approved', 'email_verified_at' => now()]);
}

it('creates an email-free printable access card without retaining the plain code', function (): void {
    $owner = sg29Operator();

    $response = $this->actingAs($owner)->post(route('admin.access.invite'), [
        'name' => 'Mary Smith',
        'email' => null,
        'username' => null,
        'role' => 'viewer',
        'family_branch_id' => null,
    ])->assertRedirect();

    $card = $response->getSession()->get('access_card');
    $invitation = UserInvitation::query()->sole();
    expect($card['username'])->toBe('mary.smith')
        ->and($card['code'])->toMatch('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/')
        ->and($invitation->email)->toBeNull()
        ->and($invitation->token_hash)->not->toContain(str_replace('-', '', $card['code']));
});

it('resolves a formatted access code and creates a verified pending managed account', function (): void {
    $owner = sg29Operator();
    $create = $this->actingAs($owner)->post(route('admin.access.invite'), [
        'name' => 'George Brown',
        'role' => 'contributor',
    ]);
    $card = $create->getSession()->get('access_card');
    $invitation = UserInvitation::query()->sole();

    auth()->logout();
    $this->post(route('access-code.find'), ['code' => strtolower($card['code'])])
        ->assertRedirect(route('invitation.show', [$invitation->invitation_id, str_replace('-', '', $card['code'])]));

    $this->post(route('invitation.accept', [$invitation->invitation_id, str_replace('-', '', $card['code'])]), [
        'password' => 'A-safe-test-password-123!',
        'password_confirmation' => 'A-safe-test-password-123!',
    ])->assertRedirect(route('account.waiting'));

    $member = User::query()->where('username', 'george.brown')->firstOrFail();
    expect($member->email)->toBeNull()
        ->and($member->email_verified_at)->not->toBeNull()
        ->and($member->account_state)->toBe('pending')
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('allows sign in by member name after approval', function (): void {
    $member = User::factory()->create([
        'username' => 'aunty.mary',
        'email' => null,
        'password' => 'A-safe-test-password-123!',
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);

    $this->post(route('login.store'), [
        'email' => 'AUNTY.MARY',
        'password' => 'A-safe-test-password-123!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($member);
});

it('lets administrators create routine access but not elevated roles', function (): void {
    $admin = sg29Operator('admin');

    $this->actingAs($admin)->post(route('admin.family-operations.invitations'), [
        'name' => 'Routine Member',
        'role' => 'viewer',
    ])->assertRedirect()->assertSessionHas('access_card');

    $this->actingAs($admin)->post(route('admin.family-operations.invitations'), [
        'name' => 'Unsafe Admin',
        'role' => 'admin',
    ])->assertSessionHasErrors('role');
});

it('uses a one-time assisted recovery code without changing access policy', function (): void {
    $admin = sg29Operator('admin');
    $member = User::factory()->create([
        'username' => 'grandad.joe',
        'email' => null,
        'role' => 'viewer',
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);
    $response = $this->actingAs($admin)->post(route('admin.family-operations.recovery', $member), [
        'reason' => 'Member requested help by telephone.',
    ])->assertRedirect();
    $card = $response->getSession()->get('access_card');
    $invitation = UserInvitation::query()->where('purpose', 'recovery')->sole();

    auth()->logout();
    $token = str_replace('-', '', $card['code']);
    $this->post(route('invitation.accept', [$invitation->invitation_id, $token]), [
        'password' => 'A-new-safe-password-123!',
        'password_confirmation' => 'A-new-safe-password-123!',
    ])->assertRedirect(route('dashboard'));

    expect(Hash::check('A-new-safe-password-123!', $member->fresh()->password))->toBeTrue()
        ->and($member->fresh()->role)->toBe('viewer')
        ->and($member->fresh()->account_state)->toBe('approved')
        ->and(AccountAccessEvent::query()->where('event_type', 'recovery_access_used')->where('user_id', $member->id)->exists())->toBeTrue();

    auth()->logout();
    $this->post(route('invitation.accept', [$invitation->invitation_id, $token]), [
        'password' => 'Another-password-123!',
        'password_confirmation' => 'Another-password-123!',
    ])->assertGone();
});

it('prevents administrators from resetting elevated accounts', function (): void {
    $admin = sg29Operator('admin');
    $owner = sg29Operator('owner');

    $this->actingAs($admin)->post(route('admin.family-operations.recovery', $owner), [
        'reason' => 'Attempted elevated recovery.',
    ])->assertForbidden();

    expect(UserInvitation::query()->where('purpose', 'recovery')->exists())->toBeFalse();
});

it('shows pending members a helpful waiting screen instead of a bare denial', function (): void {
    $member = User::factory()->create(['account_state' => 'pending', 'email_verified_at' => now()]);

    $this->actingAs($member)->get(route('archive.index'))->assertRedirect(route('account.waiting'));
    $this->actingAs($member)->get(route('account.waiting'))->assertOk()->assertSee('administrator will approve access');
});
