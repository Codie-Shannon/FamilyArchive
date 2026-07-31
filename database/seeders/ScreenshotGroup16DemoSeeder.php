<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ScreenshotGroup16DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Screenshot Group 16 demo seeding is restricted to the local environment.');
        }

        $this->call(ScreenshotGroup12DemoSeeder::class);

        $owner = User::query()->where('email', 'sg12-owner@example.test')->firstOrFail();
        $contributor = User::query()->where('email', 'sg12-contributor@example.test')->firstOrFail();

        foreach ([
            ['16000000-0000-4000-8000-000000000001', 'Harbour Family Room', 'family'],
            ['16000000-0000-4000-8000-000000000002', 'Album Identification Circle', 'invite'],
        ] as [$spaceId, $name, $visibility]) {
            DB::table('community_spaces')->updateOrInsert(
                ['space_id' => $spaceId],
                [
                    'name' => $name,
                    'visibility' => $visibility,
                    'owner_id' => $owner->id,
                    'created_at' => now()->subDays(3),
                    'updated_at' => now(),
                ],
            );
            $space = DB::table('community_spaces')->where('space_id', $spaceId)->first();
            if ($space === null) {
                throw new RuntimeException('The fictional SG16 community space was not created.');
            }
            $internalSpaceId = (int) $space->id;

            DB::table('community_memberships')->updateOrInsert(
                ['community_space_id' => $internalSpaceId, 'user_id' => $owner->id],
                ['role' => 'owner', 'suspended_at' => null, 'created_at' => now()->subDays(3), 'updated_at' => now()],
            );
            DB::table('community_memberships')->updateOrInsert(
                ['community_space_id' => $internalSpaceId, 'user_id' => $contributor->id],
                ['role' => 'member', 'suspended_at' => null, 'created_at' => now()->subDays(2), 'updated_at' => now()],
            );

            foreach ([['family-stories', 'text'], ['announcements', 'announcements']] as [$channelName, $kind]) {
                DB::table('community_channels')->updateOrInsert(
                    ['community_space_id' => $internalSpaceId, 'name' => $channelName],
                    [
                        'kind' => $kind,
                        'permission_overrides' => null,
                        'created_at' => now()->subDays(2),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        $channel = DB::table('community_channels')
            ->where('name', 'family-stories')
            ->orderBy('id')
            ->first();
        if ($channel === null) {
            throw new RuntimeException('The fictional SG16 activity channel was not created.');
        }
        $channelId = (int) $channel->id;

        DB::table('community_presence')->updateOrInsert(
            ['user_id' => $contributor->id, 'community_channel_id' => $channelId],
            [
                'state' => 'online',
                'last_seen_at' => now(),
                'typing_until' => now()->addSeconds(5),
            ],
        );
    }
}
