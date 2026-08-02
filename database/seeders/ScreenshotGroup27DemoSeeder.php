<?php

namespace Database\Seeders;

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\PublicDiscovery\Services\PublicMapPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup27DemoSeeder extends Seeder
{
    public function run(PublicMapPolicy $mapPolicy): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG27 dataset is local-only.');

        $member = User::query()->firstOrNew(['email' => 'sg27-member@example.test']);
        $member->forceFill([
            'name' => 'Mara Kauri',
            'password' => Hash::make('SG27Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'viewer',
            'account_state' => 'approved',
            'family_connection' => 'Fictional archive-map evidence identity',
        ])->save();

        $owner = User::query()->firstOrNew(['email' => 'sg27-owner@example.test']);
        $owner->forceFill([
            'name' => 'Theo Harbour',
            'password' => Hash::make('SG27Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'owner',
            'account_state' => 'approved',
            'family_connection' => 'Fictional archive-map evidence identity',
        ])->save();

        $places = [
            ['01', 'Harbour departure portrait', 'Fictional Waitematā Harbour', -36.840556, 174.739722, 'neighbourhood'],
            ['02', 'Raukawa railway homecoming', 'Fictional Wellington Region', -41.286640, 174.775570, 'town'],
            ['03', 'Southern plains family picnic', 'Fictional Canterbury Region', -43.532100, 172.636200, 'region'],
            ['04', 'Otago anniversary gathering', 'Fictional Dunedin', -45.878760, 170.502800, 'town'],
        ];

        DB::transaction(function () use ($mapPolicy, $owner, $places): void {
            foreach ($places as [$suffix, $title, $placeName, $latitude, $longitude, $precision]) {
                $media = MediaItem::query()->updateOrCreate(
                    ['archive_id' => "SG27-MAP-{$suffix}"],
                    [
                        'media_type' => MediaType::Photo,
                        'title' => $title,
                        'description' => 'Synthetic public-map evidence with deliberately reduced location precision.',
                        'story' => 'A fictional archive story created only for privacy-safe interactive map evidence.',
                        'canonical_date' => null,
                        'estimated_decade' => 1970,
                        'date_confidence' => DateConfidence::DecadeOnly,
                        'visibility' => MediaVisibility::PublicHighlightApproved,
                        'review_status' => MediaReviewStatus::Approved,
                        'sensitivity_status' => SensitivityStatus::NotFlagged,
                        'created_by' => $owner->id,
                        'approved_by' => $owner->id,
                        'approved_at' => now(),
                    ],
                );

                $entryUuid = sprintf('27000000-0000-4000-8000-%012d', (int) $suffix);
                DB::table('public_showcase_entries')->updateOrInsert(
                    ['entry_id' => $entryUuid],
                    [
                        'media_item_id' => $media->id,
                        'approved_by' => $owner->id,
                        'public_title' => $title,
                        'public_summary' => 'Synthetic portfolio content demonstrating privacy-reviewed interactive geography.',
                        'state' => 'published',
                        'published_at' => now(),
                        'allow_social_cards' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $entryId = (int) DB::table('public_showcase_entries')
                    ->where('entry_id', $entryUuid)
                    ->value('id');
                $protected = $mapPolicy->protect($latitude, $longitude, $precision);

                DB::table('public_map_points')->updateOrInsert(
                    ['public_showcase_entry_id' => $entryId],
                    [
                        'latitude' => $protected['latitude'],
                        'longitude' => $protected['longitude'],
                        'precision' => $protected['precision'],
                        'public_place_name' => $placeName,
                        'privacy_reviewed' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }
}
