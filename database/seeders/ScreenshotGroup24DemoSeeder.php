<?php

namespace Database\Seeders;

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup24DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG24 dataset is local-only.');

        $owner = $this->user('sg24-owner@example.test', 'Ariana Mercer', 'owner', 'approved');
        $admin = $this->user('sg24-admin@example.test', 'Noah Bennett', 'admin', 'approved');
        $member = $this->user('sg24-member@example.test', 'Mila Rowan', 'viewer', 'approved');
        $routineViewer = $this->user('sg24-pending-viewer@example.test', 'Elliot Fraser', 'viewer', 'pending');
        $routineContributor = $this->user('sg24-pending-contributor@example.test', 'Sofia Clarke', 'contributor', 'pending');
        $this->user('sg24-owner-exception@example.test', 'Theo Walsh', 'trusted_contributor', 'pending');

        $branch = FamilyBranch::query()->updateOrCreate(
            ['branch_id' => 'SG24-BRN-HARBOUR'],
            [
                'name' => 'Harbour Family Branch',
                'description' => 'A fictional branch used for delegated family operations evidence.',
                'is_sensitive' => false,
                'review_state' => KnowledgeReviewState::Accepted,
                'confidence' => 'high',
                'source_note' => 'Synthetic family register for SG24 evidence.',
                'review_reason' => 'Accepted for local evidence only.',
                'created_by' => $owner->id,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
                'metadata_revision' => 1,
            ],
        );

        foreach ([$routineViewer, $routineContributor] as $candidate) {
            $candidate->forceFill([
                'family_branch_id' => $branch->id,
                'family_connection' => 'Invited through the fictional Harbour family register.',
            ])->save();
        }

        $threadId = DB::table('conversation_threads')->updateOrInsert(
            ['thread_id' => 'c7601af4-61f1-4e80-91df-d8902bf29d24'],
            [
                'subject' => 'Identifying the harbour picnic album',
                'scope' => 'public',
                'entity_type' => null,
                'entity_id' => null,
                'created_by' => $member->id,
                'is_locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        unset($threadId);
        $thread = DB::table('conversation_threads')->where('thread_id', 'c7601af4-61f1-4e80-91df-d8902bf29d24')->value('id');

        DB::table('conversation_messages')->updateOrInsert(
            ['conversation_thread_id' => $thread, 'body' => 'The handwritten caption may name a living relative; please check before it is quoted publicly.'],
            [
                'author_id' => $member->id,
                'parent_id' => null,
                'moderation_state' => 'reported',
                'created_at' => now()->subMinutes(18),
                'updated_at' => now()->subMinutes(4),
            ],
        );
        DB::table('conversation_messages')->updateOrInsert(
            ['conversation_thread_id' => $thread, 'body' => 'I remember the picnic shelter and can add a safe location note.'],
            [
                'author_id' => $admin->id,
                'parent_id' => null,
                'moderation_state' => 'visible',
                'created_at' => now()->subMinutes(8),
                'updated_at' => now()->subMinutes(8),
            ],
        );

        DB::table('anonymous_messages')->updateOrInsert(
            ['message_id' => '6bd915b3-457e-46fa-ac4d-d6d13cc76d74'],
            [
                'correlation_token' => hash('sha256', 'sg24-contact-correlation'),
                'reply_email' => null,
                'subject' => 'Possible name for the picnic group',
                'body' => 'A fictional public visitor offers a possible caption and asks the family to verify it.',
                'moderation_state' => 'pending',
                'source_fingerprint' => hash('sha256', 'sg24-contact-source'),
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
        );

        DB::table('community_spaces')->updateOrInsert(
            ['space_id' => '40828e1f-a4ba-429b-80fe-45b128cd8441'],
            [
                'name' => 'Harbour Family Room',
                'visibility' => 'family',
                'owner_id' => $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $space = DB::table('community_spaces')->where('space_id', '40828e1f-a4ba-429b-80fe-45b128cd8441')->value('id');
        DB::table('community_channels')->updateOrInsert(
            ['community_space_id' => $space, 'name' => 'album-stories'],
            [
                'kind' => 'voice',
                'permission_overrides' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $channel = DB::table('community_channels')->where('community_space_id', $space)->where('name', 'album-stories')->value('id');
        DB::table('voice_messages')->updateOrInsert(
            ['message_id' => '81275322-cf03-44f8-862c-299fb63c60b4'],
            [
                'community_channel_id' => $channel,
                'user_id' => $member->id,
                'storage_key' => 'quarantine/sg24/fictional-harbour-memory.webm',
                'duration_seconds' => 52,
                'mime_type' => 'audio/webm',
                'checksum_sha256' => hash('sha256', 'sg24-fictional-voice'),
                'moderation_state' => 'pending',
                'created_at' => now()->subMinutes(32),
                'updated_at' => now()->subMinutes(32),
            ],
        );

        DB::table('public_identity_aliases')->updateOrInsert(
            ['alias_id' => 'c28dbed0-266d-441e-bfe6-0b692036df16'],
            [
                'display_name' => 'Harbour Album Researcher',
                'moderation_fingerprint' => hash('sha256', 'sg24-public-alias'),
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $alias = DB::table('public_identity_aliases')->where('alias_id', 'c28dbed0-266d-441e-bfe6-0b692036df16')->value('id');
        DB::table('public_direct_threads')->updateOrInsert(
            ['thread_id' => 'f5c74425-5145-48b7-af95-e7a1bf93872f'],
            [
                'initiator_alias_id' => $alias,
                'recipient_user_id' => $member->id,
                'state' => 'pending',
                'created_at' => now()->subMinutes(12),
                'updated_at' => now()->subMinutes(12),
            ],
        );
    }

    private function user(string $email, string $name, string $role, string $state): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('SG24Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => $state,
            'family_connection' => 'Fictional SG24 evidence identity.',
        ])->save();

        return $user;
    }
}
