<?php

use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\CuratedCollection;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->withoutVite();
});

function sg28User(string $role = 'viewer'): User
{
    return User::factory()->create([
        'role' => $role,
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);
}

function sg28ApprovedPhoto(User $owner, string $title = 'Fictional Harbour Picnic'): MediaItem
{
    return MediaItem::factory()->create([
        'title' => $title,
        'visibility' => MediaVisibility::FamilyVisible,
        'review_status' => MediaReviewStatus::Approved,
        'approved_by' => $owner->id,
        'approved_at' => now(),
        'created_by' => $owner->id,
    ]);
}

it('presents photos albums and search as the primary archive journey', function (): void {
    $member = sg28User();

    $this->actingAs($member)->get(route('archive.index'))
        ->assertOk()
        ->assertSee('>Photos<', false)
        ->assertSee('>Albums<', false)
        ->assertSee('>Search<', false)
        ->assertDontSee('>Places<', false)
        ->assertDontSee('>People<', false)
        ->assertDontSee('>Events<', false)
        ->assertDontSee('>Branches<', false);
});

it('turns accepted events and places into albums without duplicating photos', function (): void {
    $owner = sg28User('owner');
    $member = sg28User();
    $photo = sg28ApprovedPhoto($owner);
    $place = ArchiveLocation::factory()->create([
        'label' => 'Glossop Family Home',
        'subtitle' => 'The red-roofed house by the harbour',
        'address' => '14 Fictional Lane, Napier',
    ]);
    $event = ArchiveEvent::factory()->create([
        'name' => 'Harbour Family Reunion',
        'archive_location_id' => $place->id,
    ]);

    DB::table('archive_event_media')->insert([
        'archive_event_id' => $event->id,
        'media_item_id' => $photo->id,
        'confidence' => 'confirmed',
        'source_note' => 'Reviewed fictional album link.',
        'reviewed_by' => $owner->id,
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($member)->get(route('archive.albums.index'))
        ->assertOk()
        ->assertSee('Harbour Family Reunion')
        ->assertSee('Glossop Family Home')
        ->assertSee('The red-roofed house by the harbour');

    $response->assertSee(route('archive.albums.show', ['event', $event->event_id]), false)
        ->assertSee(route('archive.albums.show', ['place', $place->location_id]), false);

    $this->actingAs($member)->get(route('archive.albums.show', ['event', $event->event_id]))
        ->assertOk()
        ->assertSee($photo->title)
        ->assertSee('View details');
});

it('lets trusted contributors curate shared albums while ordinary members only browse', function (): void {
    $owner = sg28User('owner');
    $trusted = sg28User('trusted_contributor');
    $member = sg28User();
    $photo = sg28ApprovedPhoto($owner, 'Fictional Anniversary Portrait');

    $this->actingAs($member)->get(route('archive.albums.create'))->assertForbidden();

    $this->actingAs($trusted)->post(route('archive.albums.store'), [
        'name' => 'Anniversary Album',
        'description' => 'A reviewed fictional family collection.',
    ])->assertRedirect();

    $album = CuratedCollection::query()->where('name', 'Anniversary Album')->firstOrFail();

    $this->actingAs($member)->get(route('archive.albums.photos.add', $album))->assertForbidden();

    $this->actingAs($trusted)->get(route('archive.albums.photos.add', $album))
        ->assertOk()
        ->assertSee('Add photos to album')
        ->assertSee('Fictional Anniversary Portrait');

    $this->actingAs($trusted)->post(route('archive.albums.photos.attach', $album), [
        'photo_ids' => [$photo->id],
    ])->assertRedirect(route('archive.albums.show', ['album', $album->collection_id]));

    expect(DB::table('curated_collection_media')
        ->where('curated_collection_id', $album->id)
        ->where('media_item_id', $photo->id)
        ->exists())->toBeTrue();

    $this->actingAs($member)->get(route('archive.albums.show', ['album', $album->collection_id]))
        ->assertOk()
        ->assertSee('Anniversary Album')
        ->assertSee('Fictional Anniversary Portrait')
        ->assertDontSee('Add photos');
});

it('adds multiple eligible photos to an album in one batch', function (): void {
    $owner = sg28User('owner');
    $trusted = sg28User('trusted_contributor');
    $album = CuratedCollection::query()->create([
        'collection_id' => 'ALB-SG28-BATCH',
        'name' => 'Batch Album',
        'is_published' => true,
        'curated_by' => $trusted->id,
    ]);
    $photos = collect([
        sg28ApprovedPhoto($owner, 'First batch photograph'),
        sg28ApprovedPhoto($owner, 'Second batch photograph'),
        sg28ApprovedPhoto($owner, 'Third batch photograph'),
    ]);

    $this->actingAs($trusted)->post(route('archive.albums.photos.attach', $album), [
        'photo_ids' => $photos->pluck('id')->all(),
    ])->assertRedirect(route('archive.albums.show', ['album', $album->collection_id]));

    expect($album->mediaItems()->pluck('media_items.id')->sort()->values()->all())
        ->toBe($photos->pluck('id')->sort()->values()->all());
});

it('searches albums and photos together under the same access boundary', function (): void {
    $owner = sg28User('owner');
    $member = sg28User();
    $photo = sg28ApprovedPhoto($owner, 'Harbour Picnic Portrait');
    $album = CuratedCollection::query()->create([
        'collection_id' => 'ALB-SG28-HARBOUR',
        'name' => 'Harbour Memories',
        'description' => 'A fictional searchable album.',
        'is_published' => true,
        'curated_by' => $owner->id,
    ]);
    $album->mediaItems()->attach($photo->id, [
        'added_by' => $owner->id,
        'position' => 1,
    ]);

    $this->actingAs($member)->get(route('archive.knowledge', ['q' => 'Harbour']))
        ->assertOk()
        ->assertSee('Harbour Memories')
        ->assertSee('Harbour Picnic Portrait')
        ->assertSee('Search applies the same family, branch, privacy and reviewed-record boundaries');
});

it('does not expose generated albums backed only by inaccessible photos', function (): void {
    $owner = sg28User('owner');
    $member = sg28User();
    $privatePhoto = MediaItem::factory()->create([
        'title' => 'Owner Private Photograph',
        'visibility' => MediaVisibility::PrivateArchive,
        'review_status' => MediaReviewStatus::Approved,
        'approved_by' => $owner->id,
        'approved_at' => now(),
        'created_by' => $owner->id,
    ]);
    $event = ArchiveEvent::factory()->create(['name' => 'Private Album Event']);
    DB::table('archive_event_media')->insert([
        'archive_event_id' => $event->id,
        'media_item_id' => $privatePhoto->id,
        'confidence' => 'confirmed',
        'source_note' => 'Reviewed fictional private link.',
        'reviewed_by' => $owner->id,
        'reviewed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($member)->get(route('archive.albums.index'))
        ->assertOk()
        ->assertDontSee('Private Album Event')
        ->assertDontSee('Owner Private Photograph');
});
