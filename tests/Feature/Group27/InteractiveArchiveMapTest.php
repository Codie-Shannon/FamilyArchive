<?php

use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutVite();
});

function sg27PublishedMapPoint(array $attributes = []): void
{
    $owner = User::factory()->create([
        'role' => 'owner',
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);
    $media = MediaItem::factory()->create(['created_by' => $owner->id]);
    $entry = DB::table('public_showcase_entries')->insertGetId([
        'entry_id' => (string) Str::uuid(),
        'media_item_id' => $media->id,
        'approved_by' => $owner->id,
        'public_title' => $attributes['title'] ?? 'Fictional Coastal Album',
        'public_summary' => 'A fictional privacy-safe map story.',
        'state' => 'published',
        'published_at' => now(),
        'allow_social_cards' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('public_map_points')->insert([
        'public_showcase_entry_id' => $entry,
        'latitude' => $attributes['latitude'] ?? -39.123456,
        'longitude' => $attributes['longitude'] ?? 176.987654,
        'precision' => $attributes['precision'] ?? 'town',
        'public_place_name' => $attributes['place'] ?? 'Fictional Coastal District',
        'privacy_reviewed' => $attributes['reviewed'] ?? true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('uses one shared archive shell with the simplified archive navigation', function (string $routeName): void {
    $member = User::factory()->create([
        'role' => 'viewer',
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($member)->get(route($routeName))
        ->assertOk()
        ->assertSee('max-w-7xl flex-1 flex-col gap-7 p-4 sm:p-6', false)
        ->assertSee('>Photos<', false)
        ->assertSee('>Albums<', false)
        ->assertSee('>Search<', false)
        ->assertSee(route('archive.albums.index'), false);
})->with([
    'photos' => 'archive.index',
    'places' => 'archive.locations.index',
    'people' => 'archive.people.index',
    'events' => 'archive.events.index',
    'branches' => 'archive.branches.index',
    'search' => 'archive.knowledge',
    'map' => 'public-discovery.map',
]);

it('renders a configured Google map with only privacy-reduced reviewed points', function (): void {
    config()->set('services.google_maps.browser_key', 'fictional-restricted-browser-key');
    sg27PublishedMapPoint();

    $response = $this->get(route('public-discovery.map'))
        ->assertOk()
        ->assertSee('Interactive map of privacy-reviewed archive places')
        ->assertSee('maps.googleapis.com/maps/api/js', false)
        ->assertSee('Fictional Coastal District')
        ->assertSee('-39.12', false)
        ->assertSee('176.99', false)
        ->assertDontSee('-39.123456', false)
        ->assertDontSee('176.987654', false);

    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('retains the reviewed place list without silently drawing a fake map when no key exists', function (): void {
    config()->set('services.google_maps.browser_key', null);
    sg27PublishedMapPoint();

    $this->get(route('public-discovery.map'))
        ->assertOk()
        ->assertSee('Interactive map configuration pending')
        ->assertSee('Fictional Coastal District')
        ->assertDontSee('maps.googleapis.com/maps/api/js', false);
});

it('excludes exact and unreviewed points from the interactive browser payload', function (): void {
    config()->set('services.google_maps.browser_key', 'fictional-restricted-browser-key');
    sg27PublishedMapPoint([
        'title' => 'Exact Private Story',
        'place' => 'Exact Private Homestead',
        'precision' => 'exact',
    ]);
    sg27PublishedMapPoint([
        'title' => 'Unreviewed Story',
        'place' => 'Unreviewed Private Place',
        'reviewed' => false,
    ]);

    $this->get(route('public-discovery.map'))
        ->assertOk()
        ->assertDontSee('Exact Private Story')
        ->assertDontSee('Exact Private Homestead')
        ->assertDontSee('Unreviewed Story')
        ->assertDontSee('Unreviewed Private Place');
});
