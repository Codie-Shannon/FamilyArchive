<?php

use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Domain\Provenance\Enums\SourceCollectionType;
use App\Domain\Provenance\Models\MediaProvenance;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use Database\Seeders\Group12DemoSeeder;
use Illuminate\Support\Facades\Storage;

it('allows only the owner to manage stable source and scan batch records', function () {
    $owner = g12Owner();

    $this->get(route('archive.sources.index'))->assertRedirect('/login');

    $response = $this->actingAs($owner)->post(route('archive.sources.store'), [
        'type' => SourceCollectionType::PhysicalAlbum->value,
        'name' => 'Fictional Te Aro Album',
        'description' => 'Synthetic album used only for Group 12 proof.',
        'physical_reference' => 'Demo shelf B',
    ]);

    $source = SourceCollection::sole();
    $response->assertRedirect(route('archive.sources.show', $source));

    expect($source->source_id)->toStartWith('SRC-')
        ->and($source->type)->toBe(SourceCollectionType::PhysicalAlbum);

    $this->actingAs($owner)->post(
        route('archive.sources.scan-batches.store', $source),
        [
            'label' => 'Fictional pages 1-12',
            'scanned_on' => '2026-07-24',
            'notes' => 'Synthetic scanner session.',
        ]
    )->assertRedirect(route('archive.sources.show', $source));

    expect(ScanBatch::sole()->scan_batch_id)->toStartWith('SCAN-');
});

it('attaches multiple reviewed sources and records immutable revision evidence', function () {
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_quarantine');

    $owner = g12Owner();
    $photo = g12Photo($owner, 'G12-PROV-001');
    $album = SourceCollection::factory()->create(['created_by' => $owner->id]);
    $envelope = SourceCollection::factory()->create([
        'type' => SourceCollectionType::Envelope,
        'name' => 'Fictional labelled envelope',
        'created_by' => $owner->id,
    ]);
    $batch = ScanBatch::factory()->create([
        'source_collection_id' => $album->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)->post(
        route('archive.photos.provenance.store', $photo),
        [
            'expected_metadata_revision' => 0,
            'source_collection_id' => $album->id,
            'scan_batch_id' => $batch->id,
            'note' => 'Scanned from fictional album page four.',
            'change_reason' => 'Attach reviewed fictional album provenance.',
        ]
    )->assertRedirect(route('archive.photos.show', $photo));

    $this->actingAs($owner)->post(
        route('archive.photos.provenance.store', $photo),
        [
            'expected_metadata_revision' => 1,
            'source_collection_id' => $envelope->id,
            'scan_batch_id' => null,
            'note' => 'Envelope carried a matching synthetic annotation.',
            'change_reason' => 'Add second reviewed fictional source.',
        ]
    )->assertRedirect(route('archive.photos.show', $photo));

    expect(MediaProvenance::count())->toBe(2)
        ->and($photo->fresh()->metadata_revision)->toBe(2)
        ->and(PhotoMetadataRevision::count())->toBe(2)
        ->and(PhotoMetadataRevision::latest('id')->firstOrFail()->changed_fields)
        ->toBe(['source_provenance'])
        ->and(Storage::disk('archive_originals')->allFiles())->toBe([])
        ->and(Storage::disk('archive_derivatives')->allFiles())->toBe([])
        ->and(Storage::disk('archive_quarantine')->allFiles())->toBe([]);

    $revision = PhotoMetadataRevision::latest('id')->firstOrFail();
    $this->actingAs($owner)
        ->get(route('archive.photos.metadata.history.show', [$photo, $revision]))
        ->assertOk()
        ->assertSee('source provenance')
        ->assertSee($envelope->source_id);
});

it('rejects a scan batch from another source and duplicate links', function () {
    $owner = g12Owner();
    $photo = g12Photo($owner, 'G12-PROV-VALIDATE');
    $source = SourceCollection::factory()->create(['created_by' => $owner->id]);
    $other = SourceCollection::factory()->create(['created_by' => $owner->id]);
    $wrongBatch = ScanBatch::factory()->create([
        'source_collection_id' => $other->id,
        'created_by' => $owner->id,
    ]);

    $payload = [
        'expected_metadata_revision' => 0,
        'source_collection_id' => $source->id,
        'scan_batch_id' => $wrongBatch->id,
        'note' => null,
        'change_reason' => 'Attempt invalid fictional provenance.',
    ];

    $this->actingAs($owner)
        ->post(route('archive.photos.provenance.store', $photo), $payload)
        ->assertSessionHasErrors('scan_batch_id');

    $payload['scan_batch_id'] = null;
    $this->actingAs($owner)
        ->post(route('archive.photos.provenance.store', $photo), $payload)
        ->assertSessionHasNoErrors();

    $payload['expected_metadata_revision'] = 1;
    $this->actingAs($owner)
        ->post(route('archive.photos.provenance.store', $photo), $payload)
        ->assertSessionHasErrors('source_collection_id');

    expect(MediaProvenance::count())->toBe(1);
});

it('removes only the provenance link and records a new revision', function () {
    $owner = g12Owner();
    $photo = g12Photo($owner, 'G12-PROV-REMOVE');
    $source = SourceCollection::factory()->create(['created_by' => $owner->id]);
    $link = MediaProvenance::query()->create([
        'media_item_id' => $photo->id,
        'source_collection_id' => $source->id,
        'scan_batch_id' => null,
        'note' => 'Synthetic provenance link.',
        'attached_by' => $owner->id,
    ]);

    $this->actingAs($owner)->delete(
        route('archive.photos.provenance.destroy', [$photo, $link]),
        [
            'expected_metadata_revision' => 0,
            'change_reason' => 'Correct an incorrectly linked fictional source.',
        ]
    )->assertRedirect(route('archive.photos.show', $photo));

    expect(MediaProvenance::count())->toBe(0)
        ->and(SourceCollection::count())->toBe(1)
        ->and($photo->fresh()->metadata_revision)->toBe(1)
        ->and(PhotoMetadataRevision::sole()->before_values['source_provenance'])
        ->toHaveCount(1)
        ->and(PhotoMetadataRevision::sole()->after_values['source_provenance'])
        ->toBe([]);
});

it('shows collection detail without unapproved archive records', function () {
    $owner = g12Owner();
    $approved = g12Photo($owner, 'G12-VISIBLE');
    $unapproved = MediaItem::factory()->create([
        'archive_id' => 'G12-HIDDEN',
        'created_by' => $owner->id,
    ]);
    $source = SourceCollection::factory()->create(['created_by' => $owner->id]);

    foreach ([$approved, $unapproved] as $photo) {
        MediaProvenance::query()->create([
            'media_item_id' => $photo->id,
            'source_collection_id' => $source->id,
            'scan_batch_id' => null,
            'note' => null,
            'attached_by' => $owner->id,
        ]);
    }

    $this->actingAs($owner)
        ->get(route('archive.sources.show', $source))
        ->assertOk()
        ->assertSee('G12-VISIBLE')
        ->assertDontSee('G12-HIDDEN')
        ->assertSee('do not move, rename, replace or expose preserved files');
});

it('creates an isolated idempotent fictional Group 12 proof dataset', function () {
    Storage::fake('archive_derivatives');

    $this->seed(Group12DemoSeeder::class);
    $this->seed(Group12DemoSeeder::class);

    expect(MediaItem::where('archive_id', 'G12-DEMO-001')->count())->toBe(1)
        ->and(SourceCollection::where('source_id', 'SRC-G12-FICTIONAL-ALBUM')->count())->toBe(1)
        ->and(ScanBatch::where('scan_batch_id', 'SCAN-G12-FICTIONAL-001')->count())->toBe(1)
        ->and(MediaProvenance::count())->toBe(1)
        ->and(PhotoMetadataRevision::count())->toBe(2);
});
