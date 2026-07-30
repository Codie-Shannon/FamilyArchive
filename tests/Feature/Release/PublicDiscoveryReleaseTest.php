<?php

use App\Domain\Media\Models\MediaItem;
use App\Domain\PublicDiscovery\Services\PublicMapPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('reduces location precision and rejects exact public coordinates', function () {
    $policy = app(PublicMapPolicy::class);
    expect($policy->protect(-36.848461, 174.763336, 'town'))->toBe([
        'latitude' => -36.85,
        'longitude' => 174.76,
        'precision' => 'town',
    ]);
    $policy->protect(-36.848461, 174.763336, 'exact');
})->throws(InvalidArgumentException::class);

it('serves empty public discovery pages safely', function () {
    $this->withoutVite();
    $this->get(route('public-discovery.index'))->assertOk()->assertSee('Stories approved for everyone');
    $this->get(route('public-discovery.map'))->assertOk()->assertSee('Privacy-safe geography');
});

it('publishes only approved stories and privacy reviewed reduced map points', function () {
    $this->withoutVite();

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $media = MediaItem::factory()->create(['created_by' => $owner->id]);

    $publishedEntryId = DB::table('public_showcase_entries')->insertGetId([
        'entry_id' => (string) Str::uuid(),
        'media_item_id' => $media->id,
        'approved_by' => $owner->id,
        'public_title' => 'Fictional Harbour Album',
        'public_summary' => 'An approved fictional public story.',
        'state' => 'published',
        'published_at' => now(),
        'allow_social_cards' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('public_showcase_entries')->insert([
        'entry_id' => (string) Str::uuid(),
        'media_item_id' => $media->id,
        'approved_by' => $owner->id,
        'public_title' => 'Private Draft Story',
        'public_summary' => 'This draft must never reach public output.',
        'state' => 'draft',
        'published_at' => null,
        'allow_social_cards' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('public_map_points')->insert([
        [
            'public_showcase_entry_id' => $publishedEntryId,
            'latitude' => -36.85,
            'longitude' => 174.76,
            'precision' => 'town',
            'public_place_name' => 'Fictional Harbour District',
            'privacy_reviewed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'public_showcase_entry_id' => $publishedEntryId,
            'latitude' => -36.848461,
            'longitude' => 174.763336,
            'precision' => 'exact',
            'public_place_name' => 'Exact Private Homestead',
            'privacy_reviewed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'public_showcase_entry_id' => $publishedEntryId,
            'latitude' => -36.84,
            'longitude' => 174.75,
            'precision' => 'neighbourhood',
            'public_place_name' => 'Unreviewed Private Place',
            'privacy_reviewed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->get(route('public-discovery.index'))
        ->assertOk()
        ->assertSee('Fictional Harbour Album')
        ->assertDontSee('Private Draft Story');

    $this->get(route('public-discovery.map'))
        ->assertOk()
        ->assertSee('Fictional Harbour District')
        ->assertDontSee('Exact Private Homestead')
        ->assertDontSee('Unreviewed Private Place')
        ->assertDontSee('-36.848461')
        ->assertDontSee('174.763336');
});

it('restricts the publication workspace to owners', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.public-discovery'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.public-discovery'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.public-discovery'))
        ->assertOk()
        ->assertSee('Public discovery review');
});

it('keeps v1.3 release metadata aligned', function () {
    expect(config('release.version'))->toBe('1.3.0')
        ->and(config('release.name'))->toBe('Public Discovery & Archive Maps')
        ->and(config('release.groups'))->toBe('POST-V1-C')
        ->and(config('release.status'))->toBe('Screenshot Group 08 implemented — evidence pending');
});
