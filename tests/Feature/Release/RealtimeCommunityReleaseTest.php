<?php

use App\Domain\Community\Services\RealtimeStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('expires presence while keeping a current typing signal', function () {
    $now = Carbon::parse('2026-07-25 12:00:00');
    $status = app(RealtimeStatus::class)->resolve($now->copy()->subSeconds(91), $now->copy()->addSeconds(3), $now);
    expect($status)->toBe(['online' => false, 'typing' => true]);
});

it('does not claim voice infrastructure is ready without deployment configuration', function () {
    expect(app(RealtimeStatus::class)->callReadiness())->toBe([
        'calls_enabled' => false,
        'signalling_ready' => false,
        'turn_ready' => false,
    ]);
});

it('shows owners the community operations workspace', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.community-operations'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.community-operations'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.community-operations'))
        ->assertOk()
        ->assertSee('Real-time family community')
        ->assertSee('Setup required');
});

it('shows only active memberships and allowed voice messages in the community workspace', function () {
    $this->withoutVite();

    $member = User::factory()->create([
        'name' => 'Fictional Community Member',
        'role' => 'viewer',
        'email_verified_at' => now(),
    ]);
    $pendingVoiceMember = User::factory()->create([
        'name' => 'Pending Voice Member',
        'role' => 'viewer',
        'email_verified_at' => now(),
    ]);
    $outsider = User::factory()->create([
        'name' => 'Other Space Member',
        'role' => 'viewer',
        'email_verified_at' => now(),
    ]);

    $visibleSpaceId = DB::table('community_spaces')->insertGetId([
        'space_id' => (string) Str::uuid(),
        'name' => 'Fictional History Circle',
        'visibility' => 'family',
        'owner_id' => $member->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $hiddenSpaceId = DB::table('community_spaces')->insertGetId([
        'space_id' => (string) Str::uuid(),
        'name' => 'Private Other Space',
        'visibility' => 'invite',
        'owner_id' => $outsider->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('community_memberships')->insert([
        [
            'community_space_id' => $visibleSpaceId,
            'user_id' => $member->id,
            'role' => 'owner',
            'suspended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'community_space_id' => $visibleSpaceId,
            'user_id' => $pendingVoiceMember->id,
            'role' => 'member',
            'suspended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'community_space_id' => $hiddenSpaceId,
            'user_id' => $outsider->id,
            'role' => 'owner',
            'suspended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $visibleChannelId = DB::table('community_channels')->insertGetId([
        'community_space_id' => $visibleSpaceId,
        'name' => 'archive-stories',
        'kind' => 'text',
        'permission_overrides' => json_encode(['private_internal_rule' => true]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('community_channels')->insert([
        'community_space_id' => $hiddenSpaceId,
        'name' => 'hidden-channel',
        'kind' => 'text',
        'permission_overrides' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('community_presence')->insert([
        'user_id' => $member->id,
        'community_channel_id' => $visibleChannelId,
        'state' => 'online',
        'last_seen_at' => now(),
        'typing_until' => now()->addSeconds(5),
    ]);

    DB::table('voice_messages')->insert([
        [
            'message_id' => (string) Str::uuid(),
            'community_channel_id' => $visibleChannelId,
            'user_id' => $member->id,
            'storage_key' => 'private/community/allowed-message.m4a',
            'duration_seconds' => 42,
            'mime_type' => 'audio/mp4',
            'checksum_sha256' => str_repeat('a', 64),
            'moderation_state' => 'allowed',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'message_id' => (string) Str::uuid(),
            'community_channel_id' => $visibleChannelId,
            'user_id' => $pendingVoiceMember->id,
            'storage_key' => 'private/community/pending-message.m4a',
            'duration_seconds' => 35,
            'mime_type' => 'audio/mp4',
            'checksum_sha256' => str_repeat('b', 64),
            'moderation_state' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->get(route('community.index'))->assertRedirect('/login');
    $this->actingAs($member)
        ->get(route('community.index'))
        ->assertOk()
        ->assertSee('Fictional History Circle')
        ->assertSee('archive-stories')
        ->assertSee('Fictional Community Member')
        ->assertSee('Typing now')
        ->assertDontSee('Private Other Space')
        ->assertDontSee('hidden-channel')
        ->assertDontSee('Pending Voice Member')
        ->assertDontSee('private/community')
        ->assertDontSee(str_repeat('a', 64))
        ->assertDontSee('private_internal_rule');
});

it('keeps v1.4 release metadata aligned', function () {
    expect(config('release.version'))->toBe('1.4.0')
        ->and(config('release.name'))->toBe('Real-Time Family Community')
        ->and(config('release.groups'))->toBe('POST-V1-D')
        ->and(config('release.status'))->toBe('Screenshot Group 09 closed — evidence approved');
});
