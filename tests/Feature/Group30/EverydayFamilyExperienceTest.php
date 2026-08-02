<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function sg30User(string $role = 'viewer'): User
{
    return User::factory()->create(['role' => $role, 'account_state' => 'approved', 'email_verified_at' => now()]);
}

function sg30Conversation(User $recipient): array
{
    $alias = DB::table('public_identity_aliases')->insertGetId([
        'alias_id' => (string) Str::uuid(),
        'display_name' => 'Fictional Family Researcher',
        'moderation_fingerprint' => hash('sha256', 'sg30-alias'),
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $thread = DB::table('public_direct_threads')->insertGetId([
        'thread_id' => (string) Str::uuid(),
        'initiator_alias_id' => $alias,
        'recipient_user_id' => $recipient->id,
        'state' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $envelope = DB::table('encrypted_message_envelopes')->insertGetId([
        'envelope_id' => (string) Str::uuid(),
        'conversation_type' => 'public_direct_thread',
        'conversation_id' => $thread,
        'sender_user_id' => null,
        'sender_alias_id' => $alias,
        'protocol_version' => 1,
        'ciphertext' => 'SENSITIVE-SG30-CIPHERTEXT',
        'encrypted_content_key' => 'SENSITIVE-SG30-KEY',
        'content_digest' => str_repeat('a', 64),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('alias', 'thread', 'envelope');
}

it('separates public contact requests from embedded family chat', function (): void {
    $member = sg30User();
    sg30Conversation($member);

    $this->actingAs($member)->get(route('secure-messages.legacy'))
        ->assertRedirect('/contact-requests');

    $this->actingAs($member)->get(route('contact-requests.index'))
        ->assertOk()
        ->assertSee('Contact requests')
        ->assertSee('Family chat stays in the chat panel')
        ->assertSee('data-open-family-chat', false)
        ->assertDontSee('Private conversations')
        ->assertDontSee('Protocol v1')
        ->assertDontSee('Versioned envelopes')
        ->assertDontSee('Ciphertext')
        ->assertDontSee('SENSITIVE-SG30');
});

it('keeps security evidence available only to archive administrators', function (): void {
    $viewer = sg30User();
    $owner = sg30User('owner');
    sg30Conversation($viewer);
    sg30Conversation($owner);

    $this->actingAs($viewer)->get(route('contact-requests.index'))->assertDontSee('Security and audit details');
    $this->actingAs($owner)->get(route('contact-requests.index'))
        ->assertOk()
        ->assertSee('Security and audit details')
        ->assertSee('Protocol v1')
        ->assertDontSee('SENSITIVE-SG30');
});

it('uses family language while keeping community operations out of the member view', function (): void {
    $member = sg30User();
    $owner = sg30User('owner');
    $space = DB::table('community_spaces')->insertGetId([
        'space_id' => (string) Str::uuid(),
        'name' => 'Fictional Family Room',
        'visibility' => 'family',
        'owner_id' => $owner->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    foreach ([[$member, 'member'], [$owner, 'owner']] as [$user, $role]) {
        DB::table('community_memberships')->insert(['community_space_id' => $space, 'user_id' => $user->id, 'role' => $role, 'suspended_at' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    $this->actingAs($member)->get(route('community.index'))
        ->assertOk()
        ->assertSee('Your family rooms')
        ->assertSee('Fictional Family Room')
        ->assertDontSee('Expiring signals')
        ->assertDontSee('TURN infrastructure')
        ->assertDontSee('Community service details');

    $this->actingAs($owner)->get(route('community.index'))
        ->assertOk()
        ->assertSee('Community service details')
        ->assertSee('Live calls')
        ->assertSee('Not enabled');
});

it('gives contributors one clear photo-batch starting point', function (): void {
    $contributor = sg30User('contributor');

    $this->actingAs($contributor)->get(route('contributor.index'))
        ->assertOk()
        ->assertSee('Add a photo batch')
        ->assertSee('Your original photos always remain unchanged')
        ->assertSee('Your photo batches')
        ->assertDontSee('Preservation-safe contributor intake');
});
