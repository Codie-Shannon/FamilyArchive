<?php

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\DateReviewState;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function g12Owner(): User
{
    return User::factory()->create([
        'role' => 'owner',
        'email_verified_at' => now(),
    ]);
}

function g12Photo(User $owner, string $archiveId = 'G12-DATE-001'): MediaItem
{
    return MediaItem::factory()->create([
        'archive_id' => $archiveId,
        'review_status' => MediaReviewStatus::Approved,
        'approved_at' => now(),
        'approved_by' => $owner->id,
        'created_by' => $owner->id,
    ]);
}

function g12DatePayload(MediaItem $photo, array $overrides = []): array
{
    return [
        'expected_metadata_revision' => (int) $photo->metadata_revision,
        'title' => $photo->title,
        'description' => $photo->description,
        'story' => $photo->story,
        'date_precision' => DatePrecision::Exact->value,
        'canonical_date' => '1974-02-16',
        'date_year' => null,
        'estimated_decade' => null,
        'date_confidence' => DateConfidence::Confirmed->value,
        'date_review_state' => DateReviewState::Accepted->value,
        'date_source_note' => 'Date written on a fictional album page.',
        'date_reason' => 'The synthetic annotation gives a complete date.',
        'change_reason' => 'Record reviewed fictional date evidence.',
        ...$overrides,
    ];
}

it('shows the structured date form only to the verified owner', function () {
    $owner = g12Owner();
    $photo = g12Photo($owner);

    $this->get(route('archive.photos.metadata.edit', $photo))
        ->assertRedirect('/login');

    $this->actingAs($owner)
        ->get(route('archive.photos.metadata.edit', $photo))
        ->assertOk()
        ->assertSee('Structured historical date')
        ->assertSee('Embedded EXIF dates remain suggestions');
});

it('records exact dates and their evidence in one immutable revision', function () {
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_quarantine');

    $owner = g12Owner();
    $photo = g12Photo($owner, 'G12-EXACT');
    $storageBefore = [
        'originals' => Storage::disk('archive_originals')->allFiles(),
        'derivatives' => Storage::disk('archive_derivatives')->allFiles(),
        'quarantine' => Storage::disk('archive_quarantine')->allFiles(),
    ];

    $this->actingAs($owner)
        ->patch(route('archive.photos.metadata.update', $photo), g12DatePayload($photo))
        ->assertRedirect(route('archive.photos.show', $photo));

    $photo->refresh();
    $revision = PhotoMetadataRevision::sole();

    expect($photo->date_precision)->toBe(DatePrecision::Exact)
        ->and($photo->canonical_date?->format('Y-m-d'))->toBe('1974-02-16')
        ->and($photo->date_year)->toBeNull()
        ->and($photo->estimated_decade)->toBeNull()
        ->and($photo->date_confidence)->toBe(DateConfidence::Confirmed)
        ->and($photo->date_review_state)->toBe(DateReviewState::Accepted)
        ->and($photo->metadata_revision)->toBe(1)
        ->and($revision->changed_fields)->toContain(
            'date_precision',
            'canonical_date',
            'date_confidence',
            'date_source_note',
            'date_reason',
        )
        ->and(Storage::disk('archive_originals')->allFiles())->toBe($storageBefore['originals'])
        ->and(Storage::disk('archive_derivatives')->allFiles())->toBe($storageBefore['derivatives'])
        ->and(Storage::disk('archive_quarantine')->allFiles())->toBe($storageBefore['quarantine']);
});

it('supports year decade approximate and unknown representations', function (array $payload, DatePrecision $precision, ?string $date, ?int $year, ?int $decade) {
    $owner = g12Owner();
    $photo = g12Photo($owner, 'G12-'.strtoupper($precision->value));

    $this->actingAs($owner)
        ->patch(route('archive.photos.metadata.update', $photo), g12DatePayload($photo, $payload))
        ->assertSessionHasNoErrors();

    $photo->refresh();

    expect($photo->date_precision)->toBe($precision)
        ->and($photo->canonical_date?->format('Y-m-d'))->toBe($date)
        ->and($photo->date_year)->toBe($year)
        ->and($photo->estimated_decade)->toBe($decade);
})->with([
    'year only' => [[
        'date_precision' => 'year_only',
        'canonical_date' => null,
        'date_year' => 1968,
        'date_confidence' => 'high',
    ], DatePrecision::YearOnly, null, 1968, null],
    'decade only' => [[
        'date_precision' => 'decade_only',
        'canonical_date' => null,
        'estimated_decade' => 1950,
        'date_confidence' => 'low',
    ], DatePrecision::DecadeOnly, null, null, 1950],
    'approximate' => [[
        'date_precision' => 'approximate',
        'canonical_date' => '1982-06-01',
        'date_confidence' => 'medium',
    ], DatePrecision::Approximate, '1982-06-01', null, null],
    'unknown' => [[
        'date_precision' => 'unknown',
        'canonical_date' => null,
        'date_confidence' => 'unknown',
        'date_source_note' => null,
        'date_reason' => null,
    ], DatePrecision::Unknown, null, null, null],
]);

it('rejects conflicting and unsupported date facts', function (array $payload, string $field) {
    $owner = g12Owner();
    $photo = g12Photo($owner);

    $this->actingAs($owner)
        ->patch(route('archive.photos.metadata.update', $photo), g12DatePayload($photo, $payload))
        ->assertSessionHasErrors($field);

    expect($photo->fresh()->metadata_revision)->toBe(0)
        ->and(PhotoMetadataRevision::count())->toBe(0);
})->with([
    'exact without date' => [['canonical_date' => null], 'canonical_date'],
    'year with exact date' => [[
        'date_precision' => 'year_only',
        'date_year' => 1970,
    ], 'canonical_date'],
    'invalid decade' => [[
        'date_precision' => 'decade_only',
        'canonical_date' => null,
        'estimated_decade' => 1955,
    ], 'estimated_decade'],
    'future date' => [['canonical_date' => '2099-01-01'], 'canonical_date'],
    'missing source' => [['date_source_note' => null], 'date_source_note'],
]);
