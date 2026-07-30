<?php

namespace Database\Seeders;

use App\Domain\Media\Models\MediaItem;
use App\Domain\PublicDiscovery\Services\PublicMapPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class PortfolioDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Portfolio demo seeding is restricted to the local environment.');
        }

        $password = (string) config('portfolio_demo.password');

        if (blank($password)) {
            throw new RuntimeException('PORTFOLIO_DEMO_PASSWORD is required.');
        }

        $this->call(Group02DemoSeeder::class);

        $owner = User::query()
            ->where('email', (string) config('portfolio_demo.owner_email'))
            ->firstOrFail();
        $owner->forceFill(['password' => Hash::make($password)])->save();
        $media = MediaItem::query()->where('archive_id', 'FA-DEMO-00000001')->firstOrFail();
        $point = app(PublicMapPolicy::class)->protect(-41.28664, 174.77557, 'town');

        DB::transaction(function () use ($owner, $media, $point): void {
            DB::table('public_showcase_entries')->updateOrInsert(
                ['entry_id' => '00000000-0000-4000-8000-000000000160'],
                [
                    'media_item_id' => $media->id,
                    'approved_by' => $owner->id,
                    'public_title' => 'A Fictional Aotearoa Family Story',
                    'public_summary' => 'Synthetic portfolio content demonstrating reviewed publication without real family data.',
                    'state' => 'published',
                    'published_at' => now(),
                    'allow_social_cards' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $entryId = (int) DB::table('public_showcase_entries')
                ->where('entry_id', '00000000-0000-4000-8000-000000000160')
                ->value('id');

            DB::table('public_map_points')->updateOrInsert(
                ['public_showcase_entry_id' => $entryId],
                [
                    'latitude' => $point['latitude'],
                    'longitude' => $point['longitude'],
                    'precision' => $point['precision'],
                    'public_place_name' => 'Fictional Wellington Region',
                    'privacy_reviewed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
