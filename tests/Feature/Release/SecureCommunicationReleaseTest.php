<?php

use App\Domain\Communication\Services\SecureCommunicationPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('accepts complete encrypted envelopes without inspecting plaintext', function () {
    app(SecureCommunicationPolicy::class)->validateEnvelope([
        'protocol_version' => 1,
        'ciphertext' => 'fictional-ciphertext',
        'encrypted_content_key' => 'fictional-wrapped-key',
        'content_digest' => str_repeat('a', 64),
    ]);
    expect(true)->toBeTrue();
});

it('rejects incomplete encrypted envelopes', function () {
    app(SecureCommunicationPolicy::class)->validateEnvelope(['protocol_version' => 1]);
})->throws(InvalidArgumentException::class);

it('rejects non hexadecimal envelope digests', function () {
    app(SecureCommunicationPolicy::class)->validateEnvelope([
        'protocol_version' => 1,
        'ciphertext' => 'fictional-ciphertext',
        'encrypted_content_key' => 'fictional-wrapped-key',
        'content_digest' => str_repeat('z', 64),
    ]);
})->throws(InvalidArgumentException::class);

it('describes official business bridges without claiming personal chat federation', function () {
    $bridges = app(SecureCommunicationPolicy::class)->bridgeReadiness();
    expect($bridges['whatsapp']['mode'])->toBe('business_cloud_api')
        ->and($bridges['messenger']['mode'])->toBe('messenger_platform')
        ->and($bridges['whatsapp']['personal_chat_federation'])->toBeFalse()
        ->and($bridges['messenger']['personal_chat_federation'])->toBeFalse()
        ->and(config('communication_bridges.guidance_bot.may_access_private_archive'))->toBeFalse()
        ->and(config('communication_bridges.end_to_end_encryption.enabled'))->toBeFalse();
});

it('shows owners the secure communication workspace', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    DB::table('guidance_bot_interactions')->insert([
        'interaction_id' => (string) Str::uuid(),
        'user_id' => $owner->id,
        'redacted_prompt' => 'SENSITIVE REDACTED PROMPT',
        'redacted_response' => 'SENSITIVE REDACTED RESPONSE',
        'private_archive_accessed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('messaging_bridge_deliveries')->insert([
        'provider' => 'whatsapp',
        'provider_message_id' => 'SENSITIVE-PROVIDER-MESSAGE-ID',
        'direction' => 'outbound',
        'state' => 'delivered',
        'safe_metadata' => json_encode(['internal_reference' => 'SENSITIVE-BRIDGE-METADATA']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(route('admin.secure-communication'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.secure-communication'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.secure-communication'))
        ->assertOk()
        ->assertSee('Secure and federated communication')
        ->assertSee('Official integrations only')
        ->assertSee('Business Cloud Api')
        ->assertSee('Messenger Platform')
        ->assertSee('arbitrary personal chats')
        ->assertDontSee('SENSITIVE REDACTED PROMPT')
        ->assertDontSee('SENSITIVE REDACTED RESPONSE')
        ->assertDontSee('SENSITIVE-PROVIDER-MESSAGE-ID')
        ->assertDontSee('SENSITIVE-BRIDGE-METADATA');
});

it('scopes secure messages to the recipient and excludes sensitive envelope fields', function () {
    $this->withoutVite();

    $recipient = User::factory()->create([
        'name' => 'Fictional Secure Recipient',
        'role' => 'viewer',
        'email_verified_at' => now(),
    ]);
    $outsider = User::factory()->create([
        'name' => 'Fictional Outside Recipient',
        'role' => 'viewer',
        'email_verified_at' => now(),
    ]);

    $pendingAlias = DB::table('public_identity_aliases')->insertGetId([
        'alias_id' => (string) Str::uuid(),
        'display_name' => 'Harbour Historian 14',
        'moderation_fingerprint' => 'SENSITIVE-PENDING-FINGERPRINT',
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $acceptedAlias = DB::table('public_identity_aliases')->insertGetId([
        'alias_id' => (string) Str::uuid(),
        'display_name' => 'Album Helper 27',
        'moderation_fingerprint' => 'SENSITIVE-ACCEPTED-FINGERPRINT',
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $outsideAlias = DB::table('public_identity_aliases')->insertGetId([
        'alias_id' => (string) Str::uuid(),
        'display_name' => 'Private Outside Alias',
        'moderation_fingerprint' => 'SENSITIVE-OUTSIDE-FINGERPRINT',
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('public_direct_threads')->insert([
        'thread_id' => (string) Str::uuid(),
        'initiator_alias_id' => $pendingAlias,
        'recipient_user_id' => $recipient->id,
        'state' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $acceptedThread = DB::table('public_direct_threads')->insertGetId([
        'thread_id' => (string) Str::uuid(),
        'initiator_alias_id' => $acceptedAlias,
        'recipient_user_id' => $recipient->id,
        'state' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $outsideThread = DB::table('public_direct_threads')->insertGetId([
        'thread_id' => (string) Str::uuid(),
        'initiator_alias_id' => $outsideAlias,
        'recipient_user_id' => $outsider->id,
        'state' => 'accepted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $acceptedEnvelope = DB::table('encrypted_message_envelopes')->insertGetId([
        'envelope_id' => (string) Str::uuid(),
        'conversation_type' => 'public_direct_thread',
        'conversation_id' => $acceptedThread,
        'sender_user_id' => null,
        'sender_alias_id' => $acceptedAlias,
        'protocol_version' => 1,
        'ciphertext' => 'SENSITIVE-RECIPIENT-CIPHERTEXT',
        'encrypted_content_key' => 'SENSITIVE-RECIPIENT-WRAPPED-KEY',
        'content_digest' => str_repeat('a', 64),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $outsideEnvelope = DB::table('encrypted_message_envelopes')->insertGetId([
        'envelope_id' => (string) Str::uuid(),
        'conversation_type' => 'public_direct_thread',
        'conversation_id' => $outsideThread,
        'sender_user_id' => null,
        'sender_alias_id' => $outsideAlias,
        'protocol_version' => 1,
        'ciphertext' => 'SENSITIVE-OUTSIDE-CIPHERTEXT',
        'encrypted_content_key' => 'SENSITIVE-OUTSIDE-WRAPPED-KEY',
        'content_digest' => str_repeat('b', 64),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('message_attachments')->insert([
        [
            'encrypted_message_envelope_id' => $acceptedEnvelope,
            'storage_key' => 'private/secure/SENSITIVE-RECIPIENT-PATH.png',
            'original_name' => 'harbour-caption-card.png',
            'mime_type' => 'image/png',
            'bytes' => 8192,
            'checksum_sha256' => str_repeat('c', 64),
            'scan_state' => 'clean',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'encrypted_message_envelope_id' => $outsideEnvelope,
            'storage_key' => 'private/secure/SENSITIVE-OUTSIDE-PATH.pdf',
            'original_name' => 'private-outside-document.pdf',
            'mime_type' => 'application/pdf',
            'bytes' => 16384,
            'checksum_sha256' => str_repeat('d', 64),
            'scan_state' => 'clean',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->get(route('secure-messages.index'))->assertRedirect('/login');
    $this->actingAs($recipient)
        ->get(route('secure-messages.index'))
        ->assertOk()
        ->assertSee('Harbour Historian 14')
        ->assertSee('Album Helper 27')
        ->assertSee('harbour-caption-card.png')
        ->assertSee('Protocol v1')
        ->assertSee('Runtime setup required')
        ->assertDontSee('Private Outside Alias')
        ->assertDontSee('private-outside-document.pdf')
        ->assertDontSee('SENSITIVE-RECIPIENT-CIPHERTEXT')
        ->assertDontSee('SENSITIVE-RECIPIENT-WRAPPED-KEY')
        ->assertDontSee('SENSITIVE-OUTSIDE-CIPHERTEXT')
        ->assertDontSee('SENSITIVE-OUTSIDE-WRAPPED-KEY')
        ->assertDontSee('SENSITIVE-RECIPIENT-PATH')
        ->assertDontSee('SENSITIVE-OUTSIDE-PATH')
        ->assertDontSee('SENSITIVE-PENDING-FINGERPRINT')
        ->assertDontSee('SENSITIVE-ACCEPTED-FINGERPRINT')
        ->assertDontSee('SENSITIVE-OUTSIDE-FINGERPRINT')
        ->assertDontSee(str_repeat('a', 64))
        ->assertDontSee(str_repeat('b', 64))
        ->assertDontSee(str_repeat('c', 64))
        ->assertDontSee(str_repeat('d', 64));

    $this->actingAs($recipient)
        ->get(route('secure-messages.index', ['view' => 'attachments']))
        ->assertOk()
        ->assertSee('Attachment security')
        ->assertSee('Encrypted message state')
        ->assertSee('harbour-caption-card.png')
        ->assertDontSee('Direct-message consent')
        ->assertDontSee('Private Outside Alias')
        ->assertDontSee('SENSITIVE-RECIPIENT-CIPHERTEXT')
        ->assertDontSee('SENSITIVE-RECIPIENT-PATH');
});

it('keeps secure communication in the cumulative build', function () {
    expect(version_compare(config('release.version'), '1.5.0', '>='))->toBeTrue();
});
