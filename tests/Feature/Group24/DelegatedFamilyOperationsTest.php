<?php

use App\Domain\Access\Models\AccountAccessEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutVite();
});

function sg24User(string $role = 'viewer', string $state = 'approved'): User
{
    return User::factory()->create([
        'role' => $role,
        'account_state' => $state,
        'email_verified_at' => now(),
    ]);
}

it('delegates family operations to administrators without broadening member access', function (): void {
    $viewer = sg24User();
    $trusted = sg24User('trusted_contributor');
    $admin = sg24User('admin');
    $owner = sg24User('owner');

    $this->get(route('admin.family-operations.index'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.family-operations.index'))->assertForbidden();
    $this->actingAs($trusted)->get(route('admin.family-operations.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.family-operations.index'))->assertOk()->assertSee('Routine work, without an Owner bottleneck');
    $this->actingAs($owner)->get(route('admin.family-operations.index'))->assertOk()->assertSee('Owner policy boundary');
});

it('lets administrators decide routine accounts while reserving privileged roles for the owner', function (): void {
    $admin = sg24User('admin');
    $routine = sg24User('viewer', 'pending');
    $privileged = sg24User('trusted_contributor', 'pending');

    $this->actingAs($admin)->patch(route('admin.family-operations.accounts', $routine), [
        'decision' => 'approve',
        'reason' => 'Verified family invitation.',
    ])->assertRedirect()->assertSessionHas('status');

    expect($routine->fresh()->account_state)->toBe('approved')
        ->and(AccountAccessEvent::query()->where('user_id', $routine->id)->value('event_type'))->toBe('routine_account_decided');

    $this->actingAs($admin)->patch(route('admin.family-operations.accounts', $privileged), [
        'decision' => 'approve',
        'reason' => 'Attempted delegated elevation.',
    ])->assertSessionHasErrors('member');

    expect($privileged->fresh()->account_state)->toBe('pending');
});

it('publishes ordinary family conversation immediately and reviews only reported posts', function (): void {
    $author = sg24User();
    $reporter = sg24User();
    $admin = sg24User('admin');
    $thread = DB::table('conversation_threads')->insertGetId([
        'thread_id' => (string) Str::uuid(),
        'subject' => 'Harbour album memories',
        'scope' => 'family',
        'created_by' => $author->id,
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($author)->post(route('public-chat.message'), [
        'thread_id' => $thread,
        'body' => 'This photograph was taken beside the old wharf.',
    ])->assertRedirect()->assertSessionHas('status');

    $message = DB::table('conversation_messages')->first();
    expect($message->moderation_state)->toBe('visible');

    $this->actingAs($author)->patch(route('public-chat.report', $message->id))->assertSessionHasErrors('message');
    $this->actingAs($reporter)->patch(route('public-chat.report', $message->id))->assertRedirect()->assertSessionHas('status');
    expect(DB::table('conversation_messages')->where('id', $message->id)->value('moderation_state'))->toBe('reported');

    $this->actingAs($admin)->patch(route('admin.family-operations.conversations', $message->id), [
        'decision' => 'restore',
    ])->assertRedirect()->assertSessionHas('status');
    expect(DB::table('conversation_messages')->where('id', $message->id)->value('moderation_state'))->toBe('visible');
});

it('gives each recipient control of their own private-message request', function (): void {
    $recipient = sg24User();
    $outsider = sg24User();
    $alias = DB::table('public_identity_aliases')->insertGetId([
        'alias_id' => (string) Str::uuid(),
        'display_name' => 'Fictional Archive Researcher',
        'moderation_fingerprint' => hash('sha256', 'sg24-safe-alias'),
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $thread = DB::table('public_direct_threads')->insertGetId([
        'thread_id' => (string) Str::uuid(),
        'initiator_alias_id' => $alias,
        'recipient_user_id' => $recipient->id,
        'state' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($outsider)->patch(route('secure-messages.consent', $thread), ['decision' => 'accept'])->assertSessionHasErrors('thread');
    expect(DB::table('public_direct_threads')->where('id', $thread)->value('state'))->toBe('pending');

    $this->actingAs($recipient)->patch(route('secure-messages.consent', $thread), ['decision' => 'accept'])->assertRedirect()->assertSessionHas('status');
    expect(DB::table('public_direct_threads')->where('id', $thread)->value('state'))->toBe('accepted');
});

it('delegates voice and anonymous-contact exceptions to an administrator', function (): void {
    $member = sg24User();
    $admin = sg24User('admin');
    $space = DB::table('community_spaces')->insertGetId([
        'space_id' => (string) Str::uuid(),
        'name' => 'Fictional Family Space',
        'visibility' => 'family',
        'owner_id' => $admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $channel = DB::table('community_channels')->insertGetId([
        'community_space_id' => $space,
        'name' => 'family-voice',
        'kind' => 'voice',
        'permission_overrides' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $voice = DB::table('voice_messages')->insertGetId([
        'message_id' => (string) Str::uuid(),
        'community_channel_id' => $channel,
        'user_id' => $member->id,
        'storage_key' => 'quarantine/fictional-voice-message.webm',
        'duration_seconds' => 38,
        'mime_type' => 'audio/webm',
        'checksum_sha256' => hash('sha256', 'sg24-fictional-voice'),
        'moderation_state' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $anonymous = DB::table('anonymous_messages')->insertGetId([
        'message_id' => (string) Str::uuid(),
        'correlation_token' => hash('sha256', 'sg24-correlation'),
        'reply_email' => null,
        'subject' => 'Possible caption information',
        'body' => 'A fictional visitor offers context for a public photograph.',
        'moderation_state' => 'pending',
        'source_fingerprint' => hash('sha256', 'sg24-source'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)->patch(route('admin.family-operations.voice', $voice), ['decision' => 'allow'])->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.family-operations.anonymous', $anonymous), ['decision' => 'accepted'])->assertRedirect();

    expect(DB::table('voice_messages')->where('id', $voice)->value('moderation_state'))->toBe('allowed')
        ->and(DB::table('anonymous_messages')->where('id', $anonymous)->value('moderation_state'))->toBe('accepted');
});

it('keeps the owner command centre focused on privileged access exceptions', function (): void {
    $owner = sg24User('owner');
    $routine = User::factory()->create(['name' => 'Routine Pending Member', 'role' => 'viewer', 'account_state' => 'pending', 'email_verified_at' => now()]);
    $privileged = User::factory()->create(['name' => 'Privileged Pending Admin', 'role' => 'admin', 'account_state' => 'pending', 'email_verified_at' => now()]);

    $response = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();

    expect($response->viewData('queue')['accounts'])->toBe(1)
        ->and($routine->account_state)->toBe('pending')
        ->and($privileged->account_state)->toBe('pending');
});
