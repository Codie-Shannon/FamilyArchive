<?php

use App\Domain\Knowledge\Actions\ReviewArchiveEvent;
use App\Domain\Knowledge\Actions\ReviewArchiveLocation;
use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function g13BrowseOwner(): User
{
    return User::factory()->create([
        'role' => 'owner',
        'email_verified_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function g13BrowseLocationPayload(array $overrides = []): array
{
    return [
        'label' => 'Fictional Lambton Quay studio',
        'country_code' => 'NZ',
        'region' => 'Wellington',
        'locality' => 'Lambton',
        'precision' => LocationPrecision::Locality->value,
        'is_sensitive' => false,
        'review_state' => KnowledgeReviewState::Accepted->value,
        'confidence' => StructuredDateConfidence::High->value,
        'source_note' => 'Synthetic address recorded on an album sleeve.',
        'review_reason' => 'Accept the reviewed fictional location.',
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function g13BrowseEventPayload(?ArchiveLocation $location, array $overrides = []): array
{
    return [
        'name' => 'Fictional Wellington gathering',
        'type' => EventType::Reunion->value,
        'starts_on' => null,
        'ends_on' => null,
        'date_precision' => DatePrecision::YearOnly->value,
        'date_year' => 1984,
        'estimated_decade' => null,
        'date_confidence' => StructuredDateConfidence::High->value,
        'date_source_note' => 'Synthetic album caption records the year.',
        'archive_location_id' => $location?->id,
        'description' => 'A fictional event used for Group 13 browsing tests.',
        'review_state' => KnowledgeReviewState::Accepted->value,
        'review_reason' => 'Accept the reviewed fictional event.',
        ...$overrides,
    ];
}

it('keeps event and location browsing private to the verified owner with safe empty states', function () {
    $this->get(route('archive.events.index'))->assertRedirect('/login');
    $this->get(route('archive.locations.index'))->assertRedirect('/login');

    $member = User::factory()->create([
        'role' => 'pending',
        'email_verified_at' => now(),
    ]);
    $this->actingAs($member)->get(route('archive.events.index'))->assertForbidden();

    $owner = g13BrowseOwner();
    $this->actingAs($owner)
        ->get(route('archive.events.index'))
        ->assertOk()
        ->assertSee('Group 14 implemented — evidence pending')
        ->assertSee('No reviewed events yet');
    $this->actingAs($owner)
        ->get(route('archive.locations.index'))
        ->assertOk()
        ->assertSee('Group 14 implemented — evidence pending')
        ->assertSee('No reviewed locations yet');
});

it('creates reviewed locations and uncertain events through owner forms', function () {
    $owner = g13BrowseOwner();

    $this->actingAs($owner)
        ->post(route('archive.locations.store'), g13BrowseLocationPayload())
        ->assertSessionHasNoErrors();

    $location = ArchiveLocation::sole();
    $this->actingAs($owner)
        ->post(route('archive.events.store'), g13BrowseEventPayload($location))
        ->assertSessionHasNoErrors();

    $event = ArchiveEvent::sole();

    expect($event->date_precision)->toBe(DatePrecision::YearOnly)
        ->and($event->starts_on)->toBeNull()
        ->and($event->date_year)->toBe(1984);

    $this->actingAs($owner)
        ->get(route('archive.events.show', $event))
        ->assertOk()
        ->assertSee('1984')
        ->assertSee($location->location_id)
        ->assertSee('Immutable revision evidence');
});

it('redacts sensitive location labels and locality from every browse detail surface', function () {
    $owner = g13BrowseOwner();
    $location = app(ReviewArchiveLocation::class)->create(
        g13BrowseLocationPayload([
            'label' => 'Secret fictional family address',
            'locality' => 'Hidden Valley',
            'precision' => LocationPrecision::Private->value,
            'is_sensitive' => true,
        ]),
        $owner
    );
    $event = app(ReviewArchiveEvent::class)->create(
        g13BrowseEventPayload($location),
        $owner
    );

    foreach ([
        route('archive.locations.index'),
        route('archive.locations.show', $location),
        route('archive.events.index'),
        route('archive.events.show', $event),
    ] as $url) {
        $this->actingAs($owner)
            ->get($url)
            ->assertOk()
            ->assertSee('Private family location')
            ->assertDontSee('Secret fictional family address')
            ->assertDontSee('Hidden Valley');
    }
});

it('does not browse suggestion records as accepted archive knowledge', function () {
    $owner = g13BrowseOwner();
    $location = ArchiveLocation::factory()->create([
        'review_state' => KnowledgeReviewState::Suggestion,
        'label' => 'Unreviewed fictional location',
    ]);
    $event = ArchiveEvent::factory()->create([
        'review_state' => KnowledgeReviewState::Suggestion,
        'name' => 'Unreviewed fictional event',
        'archive_location_id' => null,
    ]);

    $this->actingAs($owner)
        ->get(route('archive.locations.show', $location))
        ->assertNotFound();
    $this->actingAs($owner)
        ->get(route('archive.events.show', $event))
        ->assertNotFound();
    $this->actingAs($owner)
        ->get(route('archive.events.index'))
        ->assertDontSee('Unreviewed fictional event');
});

it('shows safe reviewed source and media links without exposing storage coordinates', function () {
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_quarantine');

    $owner = g13BrowseOwner();
    $location = app(ReviewArchiveLocation::class)->create(
        g13BrowseLocationPayload(),
        $owner
    );
    $event = app(ReviewArchiveEvent::class)->create(
        g13BrowseEventPayload($location),
        $owner
    );
    $source = SourceCollection::factory()->create(['created_by' => $owner->id]);
    $batch = ScanBatch::factory()->create([
        'source_collection_id' => $source->id,
        'created_by' => $owner->id,
    ]);
    $media = MediaItem::factory()->create([
        'archive_id' => 'G13-SAFE-MEDIA',
        'review_status' => MediaReviewStatus::Approved,
        'approved_at' => now(),
        'approved_by' => $owner->id,
        'created_by' => $owner->id,
    ]);
    MediaFileVersion::factory()->create([
        'media_item_id' => $media->id,
        'version_type' => MediaFileVersionType::Original,
        'storage_disk' => 'archive_originals',
        'storage_path' => 'secret/internal/original.jpg',
        'sha256' => str_repeat('a', 64),
        'generation_status' => GenerationStatus::Ready,
    ]);

    $this->actingAs($owner)->post(
        route('archive.events.provenance.store', $event),
        [
            'expected_metadata_revision' => 1,
            'source_collection_id' => $source->id,
            'scan_batch_id' => $batch->id,
            'note' => 'Synthetic event source evidence.',
            'change_reason' => 'Attach reviewed fictional event source.',
        ]
    )->assertSessionHasNoErrors();

    $this->actingAs($owner)->post(
        route('archive.events.media.store', $event),
        [
            'expected_metadata_revision' => 2,
            'media_item_id' => $media->id,
            'confidence' => StructuredDateConfidence::High->value,
            'source_note' => 'Synthetic caption identifies this event.',
            'change_reason' => 'Attach reviewed fictional event media.',
        ]
    )->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->get(route('archive.events.show', $event))
        ->assertOk()
        ->assertSee($source->source_id)
        ->assertSee($batch->scan_batch_id)
        ->assertSee('G13-SAFE-MEDIA')
        ->assertDontSee('secret/internal/original.jpg')
        ->assertDontSee(str_repeat('a', 64));

    expect(Storage::disk('archive_originals')->allFiles())->toBe([])
        ->and(Storage::disk('archive_derivatives')->allFiles())->toBe([])
        ->and(Storage::disk('archive_quarantine')->allFiles())->toBe([]);
});

it('rejects conflicting form dates and stale reviewed updates', function () {
    $owner = g13BrowseOwner();
    $location = app(ReviewArchiveLocation::class)->create(
        g13BrowseLocationPayload(),
        $owner
    );

    $this->actingAs($owner)
        ->post(route('archive.events.store'), g13BrowseEventPayload($location, [
            'starts_on' => '1984-01-01',
        ]))
        ->assertSessionHasErrors('date_precision');

    $event = app(ReviewArchiveEvent::class)->create(
        g13BrowseEventPayload($location),
        $owner
    );
    $payload = g13BrowseEventPayload($location, [
        'name' => 'Corrected event title',
        'expected_metadata_revision' => 0,
    ]);

    $this->actingAs($owner)
        ->patch(route('archive.events.update', $event), $payload)
        ->assertSessionHasErrors('expected_metadata_revision');
});
