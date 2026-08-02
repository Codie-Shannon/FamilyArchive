<?php

use App\Domain\Communication\Models\FamilyMessage;
use App\Domain\Communication\Models\FamilyMessageParticipantSetting;
use App\Domain\Communication\Models\FamilyMessageThread;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
});

function sg31Member(string $role = 'viewer', string $state = 'approved'): User
{
    return User::factory()->create([
        'role' => $role,
        'account_state' => $state,
        'email_verified_at' => now(),
    ]);
}

it('defines approved family members without requiring a branch assignment', function (): void {
    foreach (['owner', 'admin', 'trusted_contributor', 'contributor', 'viewer'] as $role) {
        expect(sg31Member($role)->isApprovedFamilyMember())->toBeTrue();
    }

    expect(sg31Member('viewer', 'pending')->isApprovedFamilyMember())->toBeFalse()
        ->and(sg31Member('pending')->isApprovedFamilyMember())->toBeFalse();
});

it('lists every approved family account in the private installation', function (): void {
    $member = sg31Member();
    $owner = sg31Member('owner');
    $trusted = sg31Member('trusted_contributor');
    $pending = sg31Member('viewer', 'pending');

    $response = $this->actingAs($member)->getJson(route('family-messages.index'))->assertOk();

    $response->assertJsonFragment(['id' => $owner->id, 'name' => $owner->name])
        ->assertJsonFragment(['id' => $trusted->id, 'name' => $trusted->name])
        ->assertJsonMissing(['id' => $pending->id, 'name' => $pending->name]);
});

it('starts a conversation and exchanges messages without owner approval', function (): void {
    $mary = sg31Member('contributor');
    $jordan = sg31Member('viewer');

    $created = $this->actingAs($mary)->postJson(route('family-messages.threads.store'), [
        'recipient_id' => $jordan->id,
    ])->assertCreated();
    $threadId = $created->json('id');

    $this->actingAs($mary)->postJson(route('family-messages.messages.store', $threadId), [
        'message' => 'I found the photograph from the harbour picnic.',
    ])->assertCreated()->assertJsonFragment(['body' => 'I found the photograph from the harbour picnic.']);

    $this->actingAs($jordan)->postJson(route('family-messages.messages.store', $threadId), [
        'message' => 'Wonderful — please add it to the anniversary album.',
    ])->assertCreated();

    expect(FamilyMessage::query()->count())->toBe(2)
        ->and(FamilyMessage::query()->pluck('state')->unique()->all())->toBe(['visible']);
});

it('keeps conversations private to their two participants', function (): void {
    $one = sg31Member();
    $two = sg31Member();
    $outsider = sg31Member();
    $threadId = $this->actingAs($one)->postJson(route('family-messages.threads.store'), ['recipient_id' => $two->id])->json('id');

    $this->actingAs($outsider)->getJson(route('family-messages.threads.show', $threadId))->assertForbidden();
    $this->actingAs($outsider)->postJson(route('family-messages.messages.store', $threadId), ['message' => 'Not permitted'])->assertForbidden();
});

it('lets each participant mute archive and restore their own conversation', function (): void {
    $one = sg31Member();
    $two = sg31Member();
    $threadId = $this->actingAs($one)->postJson(route('family-messages.threads.store'), ['recipient_id' => $two->id])->json('id');

    $this->actingAs($one)->patchJson(route('family-messages.settings.update', $threadId), ['action' => 'mute'])
        ->assertOk()->assertJson(['muted' => true, 'archived' => false, 'blocked' => false]);
    $this->actingAs($one)->patchJson(route('family-messages.settings.update', $threadId), ['action' => 'archive'])
        ->assertOk()->assertJson(['muted' => true, 'archived' => true]);
    $this->actingAs($one)->patchJson(route('family-messages.settings.update', $threadId), ['action' => 'unarchive'])
        ->assertOk()->assertJson(['archived' => false]);

    $thread = FamilyMessageThread::query()->where('thread_id', $threadId)->firstOrFail();
    expect(FamilyMessageParticipantSetting::query()->where('thread_id', $thread->id)->where('user_id', $two->id)->firstOrFail()->muted_at)->toBeNull();
});

it('stops both directions after either participant blocks a conversation', function (): void {
    $one = sg31Member();
    $two = sg31Member();
    $threadId = $this->actingAs($one)->postJson(route('family-messages.threads.store'), ['recipient_id' => $two->id])->json('id');

    $this->actingAs($two)->patchJson(route('family-messages.settings.update', $threadId), ['action' => 'block'])->assertOk();
    $this->actingAs($one)->postJson(route('family-messages.messages.store', $threadId), ['message' => 'Blocked'])->assertUnprocessable();
    $this->actingAs($two)->patchJson(route('family-messages.settings.update', $threadId), ['action' => 'unblock'])->assertOk();
    $this->actingAs($one)->postJson(route('family-messages.messages.store', $threadId), ['message' => 'Available again'])->assertCreated();
});

it('allows recipients to report a message but not their own message', function (): void {
    $one = sg31Member();
    $two = sg31Member();
    $threadId = $this->actingAs($one)->postJson(route('family-messages.threads.store'), ['recipient_id' => $two->id])->json('id');
    $messageId = $this->actingAs($one)->postJson(route('family-messages.messages.store', $threadId), ['message' => 'A message for review'])->json('messages.0.id');

    $this->actingAs($one)->patchJson(route('family-messages.messages.report', $messageId))->assertUnprocessable();
    $this->actingAs($two)->patchJson(route('family-messages.messages.report', $messageId))->assertOk();

    expect(FamilyMessage::query()->where('message_id', $messageId)->value('state'))->toBe('reported');
});

it('shows only reported private messages to family administrators', function (): void {
    $one = sg31Member();
    $two = sg31Member();
    $admin = sg31Member('admin');
    $threadId = $this->actingAs($one)->postJson(route('family-messages.threads.store'), ['recipient_id' => $two->id])->json('id');
    $messageId = $this->actingAs($one)->postJson(route('family-messages.messages.store', $threadId), ['message' => 'Reported private exception'])->json('messages.0.id');
    $this->actingAs($two)->patchJson(route('family-messages.messages.report', $messageId))->assertOk();
    $message = FamilyMessage::query()->where('message_id', $messageId)->firstOrFail();

    $this->actingAs($admin)->get(route('admin.family-operations.index'))
        ->assertOk()->assertSeeText('Reported private exception');
    $this->actingAs($admin)->patch(route('admin.family-operations.private-messages', $message), ['decision' => 'hide'])
        ->assertRedirect()->assertSessionHas('status');

    expect($message->fresh()->state)->toBe('removed');
});

it('embeds the family chat launcher in approved member pages', function (): void {
    $member = sg31Member();

    $this->actingAs($member)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Open family messages')
        ->assertSee('Approved family members only');
});
