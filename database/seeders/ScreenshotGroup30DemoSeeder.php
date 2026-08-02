<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup30DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG30 dataset is local-only.');

        $owner = $this->user('sg30-owner@example.test', 'Jordan Vale', 'owner');
        $member = $this->user('sg30-member@example.test', 'Aunty Mary', 'contributor');
        $helper = $this->user('sg30-helper@example.test', 'Morgan Harbour', 'viewer');

        $spaceId = DB::table('community_spaces')->updateOrInsert(
            ['space_id' => '30000000-0000-4000-8000-000000000001'],
            ['name' => 'Harbour Family Room', 'visibility' => 'family', 'owner_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()],
        );
        unset($spaceId);
        $space = (int) DB::table('community_spaces')->where('space_id', '30000000-0000-4000-8000-000000000001')->value('id');

        foreach ([[$owner, 'owner'], [$member, 'member'], [$helper, 'member']] as [$user, $role]) {
            DB::table('community_memberships')->updateOrInsert(
                ['community_space_id' => $space, 'user_id' => $user->id],
                ['role' => $role, 'suspended_at' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        DB::table('community_channels')->updateOrInsert(
            ['community_space_id' => $space, 'name' => 'family-stories'],
            ['kind' => 'voice', 'permission_overrides' => null, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('community_channels')->updateOrInsert(
            ['community_space_id' => $space, 'name' => 'photo-memories'],
            ['kind' => 'text', 'permission_overrides' => null, 'created_at' => now(), 'updated_at' => now()],
        );
        $voiceChannel = (int) DB::table('community_channels')->where('community_space_id', $space)->where('name', 'family-stories')->value('id');
        $textChannel = (int) DB::table('community_channels')->where('community_space_id', $space)->where('name', 'photo-memories')->value('id');

        DB::table('community_presence')->updateOrInsert(
            ['user_id' => $member->id, 'community_channel_id' => $textChannel],
            ['state' => 'online', 'last_seen_at' => now(), 'typing_until' => now()->addMinutes(10)],
        );
        DB::table('community_presence')->updateOrInsert(
            ['user_id' => $helper->id, 'community_channel_id' => $textChannel],
            ['state' => 'online', 'last_seen_at' => now(), 'typing_until' => null],
        );
        DB::table('voice_messages')->updateOrInsert(
            ['message_id' => '30000000-0000-4000-8000-000000000002'],
            [
                'community_channel_id' => $voiceChannel,
                'user_id' => $helper->id,
                'storage_key' => 'private/community/sg30-fictional-voice.m4a',
                'duration_seconds' => 74,
                'mime_type' => 'audio/mp4',
                'checksum_sha256' => hash('sha256', 'sg30-fictional-voice'),
                'moderation_state' => 'allowed',
                'created_at' => now()->subMinutes(18),
                'updated_at' => now()->subMinutes(18),
            ],
        );

        $pendingAlias = $this->alias('30000000-0000-4000-8000-000000000003', 'Harbour Album Researcher');
        $acceptedAlias = $this->alias('30000000-0000-4000-8000-000000000004', 'Family History Helper');
        $this->thread('30000000-0000-4000-8000-000000000005', $pendingAlias, $member->id, 'pending');
        $acceptedThread = $this->thread('30000000-0000-4000-8000-000000000006', $acceptedAlias, $member->id, 'accepted');

        DB::table('encrypted_message_envelopes')->updateOrInsert(
            ['envelope_id' => '30000000-0000-4000-8000-000000000007'],
            [
                'conversation_type' => 'public_direct_thread',
                'conversation_id' => $acceptedThread,
                'sender_user_id' => null,
                'sender_alias_id' => $acceptedAlias,
                'protocol_version' => 1,
                'ciphertext' => 'fictional-sg30-ciphertext',
                'encrypted_content_key' => 'fictional-sg30-wrapped-key',
                'content_digest' => hash('sha256', 'fictional-sg30-message'),
                'created_at' => now()->subMinutes(9),
                'updated_at' => now()->subMinutes(9),
            ],
        );
        $envelope = (int) DB::table('encrypted_message_envelopes')->where('envelope_id', '30000000-0000-4000-8000-000000000007')->value('id');
        DB::table('message_attachments')->updateOrInsert(
            ['encrypted_message_envelope_id' => $envelope, 'original_name' => 'harbour-caption-notes.pdf'],
            [
                'storage_key' => 'private/secure/sg30-fictional-caption-notes.pdf',
                'mime_type' => 'application/pdf',
                'bytes' => 18432,
                'checksum_sha256' => hash('sha256', 'sg30-fictional-attachment'),
                'scan_state' => 'clean',
                'created_at' => now()->subMinutes(8),
                'updated_at' => now()->subMinutes(8),
            ],
        );
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'username' => 'sg30.'.str($name)->lower()->replace(' ', '.')->toString(),
            'password' => Hash::make('SG30Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG30 family member.',
        ])->save();

        return $user;
    }

    private function alias(string $aliasId, string $name): int
    {
        DB::table('public_identity_aliases')->updateOrInsert(
            ['alias_id' => $aliasId],
            ['display_name' => $name, 'moderation_fingerprint' => hash('sha256', $aliasId), 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::table('public_identity_aliases')->where('alias_id', $aliasId)->value('id');
    }

    private function thread(string $threadId, int $aliasId, int $recipientId, string $state): int
    {
        DB::table('public_direct_threads')->updateOrInsert(
            ['thread_id' => $threadId],
            ['initiator_alias_id' => $aliasId, 'recipient_user_id' => $recipientId, 'state' => $state, 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::table('public_direct_threads')->where('thread_id', $threadId)->value('id');
    }
}
