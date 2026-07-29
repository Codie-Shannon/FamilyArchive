<?php

use App\Domain\Knowledge\Actions\AttachEventProvenance;
use App\Domain\Knowledge\Actions\ReviewArchiveEvent;
use App\Domain\Knowledge\Actions\ReviewArchiveLocation;
use App\Domain\Knowledge\Enums\EventType;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Models\ArchiveEventRevision;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchiveLocationRevision;
use App\Domain\Knowledge\Models\EventProvenance;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function g13Owner(): User
{
    return User::factory()->create([
        'role' => 'owner',
        'email_verified_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function g13LocationPayload(array $overrides = []): array
{
    return [
        'label' => 'Fictional Te Aro studio',
        'country_code' => 'nz',
        'region' => 'Wellington',
        'locality' => 'Te Aro',
        'precision' => LocationPrecision::Locality->value,
        'is_sensitive' => false,
        'review_state' => KnowledgeReviewState::Accepted->value,
        'confidence' => StructuredDateConfidence::High->value,
        'source_note' => 'Location written on a synthetic album sleeve.',
        'review_reason' => 'Accept reviewed fictional location evidence.',
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function g13EventPayload(?ArchiveLocation $location, array $overrides = []): array
{
    return [
        'name' => 'Fictional 1978 family gathering',
        'type' => EventType::Reunion->value,
        'starts_on' => null,
        'ends_on' => null,
        'date_precision' => DatePrecision::YearOnly->value,
        'date_year' => 1978,
        'estimated_decade' => null,
        'date_confidence' => StructuredDateConfidence::High->value,
        'date_source_note' => 'Year appears on a synthetic album caption.',
        'archive_location_id' => $location?->id,
        'description' => 'Synthetic Group 13 event.',
        'review_state' => KnowledgeReviewState::Accepted->value,
        'review_reason' => 'Accept reviewed fictional event evidence.',
        ...$overrides,
    ];
}

it('adds Group 13 review and revision columns without activating later-group tables', function () {
    expect(Schema::hasColumns('archive_locations', [
        'review_state',
        'confidence',
        'source_note',
        'review_reason',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'metadata_revision',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('archive_events', [
            'date_precision',
            'date_year',
            'estimated_decade',
            'date_confidence',
            'date_source_note',
            'review_state',
            'review_reason',
            'created_by',
            'reviewed_by',
            'reviewed_at',
            'metadata_revision',
        ]))->toBeTrue()
        ->and(Schema::hasTable('archive_event_provenance_links'))->toBeTrue()
        ->and(Schema::hasTable('archive_event_revisions'))->toBeTrue()
        ->and(Schema::hasTable('archive_location_revisions'))->toBeTrue();
});

it('creates reviewed locations with stable identity and immutable revision evidence', function () {
    $owner = g13Owner();
    $location = app(ReviewArchiveLocation::class)->create(
        g13LocationPayload(),
        $owner
    );
    $revision = ArchiveLocationRevision::sole();

    expect($location->location_id)->toStartWith('LOC-')
        ->and($location->country_code)->toBe('NZ')
        ->and($location->precision)->toBe(LocationPrecision::Locality)
        ->and($location->review_state)->toBe(KnowledgeReviewState::Accepted)
        ->and($location->created_by)->toBe($owner->id)
        ->and($location->reviewed_by)->toBe($owner->id)
        ->and($location->metadata_revision)->toBe(1)
        ->and($revision->from_revision)->toBe(0)
        ->and($revision->to_revision)->toBe(1)
        ->and($revision->after_values['source_note'])
        ->toBe('Location written on a synthetic album sleeve.');

    expect(fn () => $revision->update(['change_reason' => 'Mutated']))
        ->toThrow(LogicException::class, 'Location revisions are immutable.');
});

it('requires private precision for sensitive locations', function () {
    $owner = g13Owner();

    expect(fn () => app(ReviewArchiveLocation::class)->create(
        g13LocationPayload(['is_sensitive' => true]),
        $owner
    ))->toThrow(ValidationException::class);

    $location = app(ReviewArchiveLocation::class)->create(
        g13LocationPayload([
            'precision' => LocationPrecision::Private->value,
            'is_sensitive' => true,
            'label' => 'Private family home',
        ]),
        $owner
    );

    expect($location->is_sensitive)->toBeTrue()
        ->and($location->precision)->toBe(LocationPrecision::Private);
});

it('represents uncertain event dates without manufacturing exact dates', function (
    array $overrides,
    DatePrecision $precision,
    ?string $start,
    ?int $year,
    ?int $decade
) {
    $owner = g13Owner();
    $location = app(ReviewArchiveLocation::class)->create(g13LocationPayload(), $owner);
    $event = app(ReviewArchiveEvent::class)->create(
        g13EventPayload($location, $overrides),
        $owner
    );

    expect($event->date_precision)->toBe($precision)
        ->and($event->starts_on?->format('Y-m-d'))->toBe($start)
        ->and($event->date_year)->toBe($year)
        ->and($event->estimated_decade)->toBe($decade)
        ->and($event->metadata_revision)->toBe(1)
        ->and(ArchiveEventRevision::where('archive_event_id', $event->id)->count())->toBe(1);
})->with([
    'exact' => [[
        'starts_on' => '1978-02-11',
        'date_precision' => DatePrecision::Exact->value,
        'date_year' => null,
    ], DatePrecision::Exact, '1978-02-11', null, null],
    'year only' => [[], DatePrecision::YearOnly, null, 1978, null],
    'decade only' => [[
        'date_precision' => DatePrecision::DecadeOnly->value,
        'date_year' => null,
        'estimated_decade' => 1970,
        'date_confidence' => StructuredDateConfidence::Low->value,
    ], DatePrecision::DecadeOnly, null, null, 1970],
    'unknown' => [[
        'date_precision' => DatePrecision::Unknown->value,
        'date_year' => null,
        'date_confidence' => StructuredDateConfidence::Unknown->value,
        'date_source_note' => null,
    ], DatePrecision::Unknown, null, null, null],
]);

it('rejects conflicting event date facts and unreviewed locations', function () {
    $owner = g13Owner();
    $accepted = app(ReviewArchiveLocation::class)->create(g13LocationPayload(), $owner);

    expect(fn () => app(ReviewArchiveEvent::class)->create(
        g13EventPayload($accepted, ['starts_on' => '1978-01-01']),
        $owner
    ))->toThrow(ValidationException::class);

    $suggested = ArchiveLocation::factory()->create([
        'review_state' => KnowledgeReviewState::Suggestion,
    ]);

    expect(fn () => app(ReviewArchiveEvent::class)->create(
        g13EventPayload($suggested),
        $owner
    ))->toThrow(ValidationException::class);
});

it('updates reviewed facts with optimistic locking and append-only revisions', function () {
    $owner = g13Owner();
    $location = app(ReviewArchiveLocation::class)->create(g13LocationPayload(), $owner);
    $event = app(ReviewArchiveEvent::class)->create(g13EventPayload($location), $owner);

    $updated = app(ReviewArchiveEvent::class)->update(
        $event,
        g13EventPayload($location, [
            'name' => 'Corrected fictional 1978 gathering',
            'review_reason' => 'Correct the reviewed synthetic event title.',
        ]),
        1,
        $owner
    );

    expect($updated->metadata_revision)->toBe(2)
        ->and($updated->name)->toBe('Corrected fictional 1978 gathering')
        ->and(ArchiveEventRevision::where('archive_event_id', $event->id)->count())->toBe(2)
        ->and(ArchiveEventRevision::latest('id')->firstOrFail()->changed_fields)
        ->toContain('name');

    expect(fn () => app(ReviewArchiveEvent::class)->update(
        $updated,
        g13EventPayload($location),
        1,
        $owner
    ))->toThrow(ValidationException::class);
});

it('links existing source provenance without mutating preserved storage', function () {
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_quarantine');

    $owner = g13Owner();
    $location = app(ReviewArchiveLocation::class)->create(g13LocationPayload(), $owner);
    $event = app(ReviewArchiveEvent::class)->create(g13EventPayload($location), $owner);
    $source = SourceCollection::factory()->create(['created_by' => $owner->id]);
    $batch = ScanBatch::factory()->create([
        'source_collection_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    app(AttachEventProvenance::class)->handle(
        $event,
        $source,
        $batch,
        'Synthetic album page links this event.',
        'Attach reviewed fictional event provenance.',
        1,
        $owner
    );

    expect(EventProvenance::count())->toBe(1)
        ->and($event->fresh()->metadata_revision)->toBe(2)
        ->and(ArchiveEventRevision::where('archive_event_id', $event->id)->count())->toBe(2)
        ->and(ArchiveEventRevision::latest('id')->firstOrFail()->changed_fields)
        ->toBe(['source_provenance'])
        ->and(Storage::disk('archive_originals')->allFiles())->toBe([])
        ->and(Storage::disk('archive_derivatives')->allFiles())->toBe([])
        ->and(Storage::disk('archive_quarantine')->allFiles())->toBe([]);
});
