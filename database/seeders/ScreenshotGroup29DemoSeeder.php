<?php

namespace Database\Seeders;

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ScreenshotGroup29DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG29 dataset is local-only.');

        $owner = $this->user('sg29-owner@example.test', 'Jordan Vale', 'jordan.vale', 'owner', 'approved');
        $admin = $this->user('sg29-admin@example.test', 'Morgan Harbour', 'morgan.harbour', 'admin', 'approved');
        $member = $this->user(null, 'Aunty Mary', 'aunty.mary', 'viewer', 'approved');
        $pending = $this->user(null, 'George Brown', 'george.brown', 'viewer', 'pending');

        FamilyBranch::query()->updateOrCreate(['branch_id' => 'SG29-BRN-HARBOUR'], [
            'name' => 'Harbour Family',
            'description' => 'A fictional branch used for guided access evidence.',
            'is_sensitive' => false,
            'review_state' => KnowledgeReviewState::Accepted,
            'source_note' => 'Synthetic SG29 family register.',
            'created_by' => $owner->id,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
            'metadata_revision' => 1,
        ]);

        UserInvitation::query()->updateOrCreate(['invitation_id' => '29000000-0000-4000-8000-000000000001'], [
            'name' => 'Ruth Harbour',
            'username' => 'ruth.harbour',
            'email' => null,
            'role' => 'contributor',
            'purpose' => 'setup',
            'target_user_id' => null,
            'token_hash' => hash('sha256', 'SG29FICTIONAL'),
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
        ]);

        AccountAccessEvent::query()->firstOrCreate([
            'user_id' => $member->id,
            'event_type' => 'guided_access_confirmed',
        ], [
            'actor_id' => $admin->id,
            'new_values' => ['username' => $member->username, 'email_required' => false],
            'reason' => 'Synthetic managed-access evidence.',
            'created_at' => now()->subMinutes(12),
        ]);
        AccountAccessEvent::query()->firstOrCreate([
            'user_id' => $pending->id,
            'event_type' => 'invitation_accepted',
        ], [
            'actor_id' => $admin->id,
            'new_values' => ['role' => 'viewer', 'account_state' => 'pending'],
            'reason' => 'Synthetic pending guided-access evidence.',
            'created_at' => now()->subMinutes(4),
        ]);
    }

    private function user(?string $email, string $name, string $username, string $role, string $state): User
    {
        $user = User::query()->firstOrNew(['username' => $username]);
        $user->forceFill([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('SG29Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => $state,
            'remember_token' => Str::random(10),
            'family_connection' => 'Fictional SG29 family member',
        ])->save();

        return $user;
    }
}
