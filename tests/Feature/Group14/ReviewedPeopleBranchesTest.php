<?php

use App\Domain\Knowledge\Actions\AttachFamilyBranchProvenance;
use App\Domain\Knowledge\Actions\AttachPersonProvenance;
use App\Domain\Knowledge\Actions\ReviewArchivePerson;
use App\Domain\Knowledge\Actions\ReviewFamilyBranch;
use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\ArchivePersonRevision;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Knowledge\Models\FamilyBranchRevision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use App\Domain\Provenance\Models\ScanBatch;
use App\Domain\Provenance\Models\SourceCollection;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function g14Owner(): User
{
    return User::factory()->create([
        'role' => 'owner',
        'email_verified_at' => now(),
    ]);
}

/** @return array<string, mixed> */
function g14BranchPayload(array $overrides = []): array
{
    return [
        ...[
            'name' => 'Fictional Kauri Branch',
            'description' => 'A synthetic branch supported by a fictional family register.',
            'is_sensitive' => false,
            'review_state' => KnowledgeReviewState::Accepted->value,
            'confidence' => StructuredDateConfidence::High->value,
            'source_note' => 'Synthetic branch register entry.',
            'review_reason' => 'Accept the reviewed fictional family branch.',
        ],
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function g14PersonPayload(FamilyBranch $branch, array $overrides = []): array
{
    return [
        ...[
            'display_name' => 'Aroha Example',
            'alternate_names' => ['A. Example', 'Possibly Aroha E.'],
            'name_certainty' => PersonNameCertainty::Probable->value,
            'birth_on' => null,
            'birth_year' => 1912,
            'birth_decade' => null,
            'birth_precision' => PersonDatePrecision::YearOnly->value,
            'death_on' => null,
            'death_year' => null,
            'death_decade' => 1980,
            'death_precision' => PersonDatePrecision::DecadeOnly->value,
            'life_state' => 'deceased',
            'fact_confidence' => StructuredDateConfidence::Medium->value,
            'source_note' => 'Synthetic album annotation and register entry.',
            'is_private' => false,
            'family_branch_id' => $branch->id,
            'notes' => 'The spelling of the alternate name remains uncertain.',
            'review_state' => KnowledgeReviewState::Accepted->value,
            'review_reason' => 'Accept reviewed fictional identity evidence.',
        ],
        ...$overrides,
    ];
}

it('adds the reviewed people and family branch schema without activating later capabilities', function () {
    expect(Schema::hasColumns('family_branches', [
        'branch_id',
        'is_sensitive',
        'review_state',
        'confidence',
        'source_note',
        'metadata_revision',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('archive_people', [
            'person_id',
            'name_certainty',
            'birth_on',
            'birth_year',
            'birth_decade',
            'birth_precision',
            'death_on',
            'death_year',
            'death_decade',
            'death_precision',
            'fact_confidence',
            'review_state',
            'metadata_revision',
        ]))->toBeTrue()
        ->and(Schema::hasTable('archive_person_revisions'))->toBeTrue()
        ->and(Schema::hasTable('family_branch_revisions'))->toBeTrue()
        ->and(Schema::hasTable('archive_person_provenance_links'))->toBeTrue()
        ->and(Schema::hasTable('family_branch_provenance_links'))->toBeTrue()
        ->and(route('archive.people.index'))->toContain('/archive/people')
        ->and(route('archive.branches.index'))->toContain('/archive/family-branches');
});

it('creates reviewed stable records with immutable first revisions', function () {
    $owner = g14Owner();
    $branch = app(ReviewFamilyBranch::class)->create(g14BranchPayload(), $owner);
    $person = app(ReviewArchivePerson::class)->create(g14PersonPayload($branch), $owner);

    expect($branch->branch_id)->toStartWith('BRN-')
        ->and($branch->metadata_revision)->toBe(1)
        ->and($branch->revisions)->toHaveCount(1)
        ->and($person->person_id)->toStartWith('PER-')
        ->and($person->identity_state)->toBe('confirmed')
        ->and($person->metadata_revision)->toBe(1)
        ->and($person->revisions)->toHaveCount(1)
        ->and($person->alternate_names)->toBe(['A. Example', 'Possibly Aroha E.']);

    expect(fn () => $person->revisions->first()->delete())
        ->toThrow(LogicException::class, 'Person revisions are immutable.');
    expect(fn () => $branch->revisions->first()->update(['change_reason' => 'Changed']))
        ->toThrow(LogicException::class, 'Family branch revisions are immutable.');
});

it('preserves incomplete life-date evidence and rejects manufactured precision', function () {
    $owner = g14Owner();
    $branch = app(ReviewFamilyBranch::class)->create(g14BranchPayload(), $owner);

    $person = app(ReviewArchivePerson::class)->create(g14PersonPayload($branch), $owner);

    expect($person->birth_precision)->toBe(PersonDatePrecision::YearOnly)
        ->and($person->birth_year)->toBe(1912)
        ->and($person->birth_on)->toBeNull()
        ->and($person->death_precision)->toBe(PersonDatePrecision::DecadeOnly)
        ->and($person->death_decade)->toBe(1980)
        ->and($person->death_year)->toBeNull();

    expect(fn () => app(ReviewArchivePerson::class)->create(
        g14PersonPayload($branch, ['birth_on' => '1912-01-01']),
        $owner
    ))->toThrow(ValidationException::class);

    expect(fn () => app(ReviewArchivePerson::class)->create(
        g14PersonPayload($branch, [
            'life_state' => 'living',
            'death_precision' => PersonDatePrecision::YearOnly->value,
            'death_year' => 2020,
            'death_decade' => null,
        ]),
        $owner
    ))->toThrow(ValidationException::class);
});

it('updates reviewed records with optimistic locking and append-only evidence', function () {
    $owner = g14Owner();
    $branch = app(ReviewFamilyBranch::class)->create(g14BranchPayload(), $owner);
    $person = app(ReviewArchivePerson::class)->create(g14PersonPayload($branch), $owner);

    $updated = app(ReviewArchivePerson::class)->update(
        $person,
        g14PersonPayload($branch, [
            'display_name' => 'Aroha Example (probable)',
            'review_reason' => 'Record the uncertainty visible in a second fictional source.',
        ]),
        1,
        $owner
    );

    expect($updated->metadata_revision)->toBe(2)
        ->and($updated->revisions()->count())->toBe(2)
        ->and($updated->revisions()->latest('revision_number')->first()?->changed_fields)
        ->toContain('display_name');

    expect(fn () => app(ReviewArchivePerson::class)->update(
        $updated,
        g14PersonPayload($branch),
        1,
        $owner
    ))->toThrow(ValidationException::class);
});

it('browses only accepted identities and redacts sensitive person facts', function () {
    $owner = g14Owner();
    $branch = app(ReviewFamilyBranch::class)->create(g14BranchPayload(), $owner);
    $public = app(ReviewArchivePerson::class)->create(g14PersonPayload($branch), $owner);
    $private = app(ReviewArchivePerson::class)->create(g14PersonPayload($branch, [
        'display_name' => 'Secret Fictional Living Person',
        'alternate_names' => ['Secret Alias'],
        'birth_year' => 1990,
        'death_decade' => null,
        'death_precision' => PersonDatePrecision::Unknown->value,
        'life_state' => 'living',
        'notes' => 'Secret fictional details.',
        'is_private' => true,
        'review_reason' => 'Accept sensitive fictional identity with browse redaction.',
    ]), $owner);
    $suggestion = ArchivePerson::factory()->create([
        'family_branch_id' => $branch->id,
        'review_state' => KnowledgeReviewState::Suggestion,
        'display_name' => 'Unreviewed Fictional Person',
    ]);

    $this->actingAs($owner)
        ->get(route('archive.people.index'))
        ->assertOk()
        ->assertSee($public->display_name)
        ->assertSee('Restricted person record')
        ->assertDontSee($private->display_name)
        ->assertDontSee('Unreviewed Fictional Person');

    $this->actingAs($owner)
        ->get(route('archive.people.show', $private))
        ->assertOk()
        ->assertSee('Sensitive person boundary')
        ->assertDontSee($private->display_name)
        ->assertDontSee('Secret Alias')
        ->assertDontSee('Secret fictional details.');

    $this->actingAs($owner)
        ->get(route('archive.people.show', $suggestion))
        ->assertNotFound();
});

it('redacts sensitive family branches and their member list', function () {
    $owner = g14Owner();
    $branch = app(ReviewFamilyBranch::class)->create(g14BranchPayload([
        'name' => 'Secret Fictional Branch',
        'description' => 'Secret fictional branch details.',
        'is_sensitive' => true,
        'review_reason' => 'Accept sensitive fictional branch with browse redaction.',
    ]), $owner);
    $person = app(ReviewArchivePerson::class)->create(g14PersonPayload($branch), $owner);

    $this->actingAs($owner)
        ->get(route('archive.branches.show', $branch))
        ->assertOk()
        ->assertSee('Restricted family branch')
        ->assertSee('Sensitive branch boundary')
        ->assertDontSee($branch->name)
        ->assertDontSee($branch->description)
        ->assertDontSee($person->display_name);
});

it('attaches reviewed provenance and records it as a new immutable revision', function () {
    $owner = g14Owner();
    $branch = app(ReviewFamilyBranch::class)->create(g14BranchPayload(), $owner);
    $person = app(ReviewArchivePerson::class)->create(g14PersonPayload($branch), $owner);
    $source = SourceCollection::factory()->create(['created_by' => $owner->id]);
    $batch = ScanBatch::factory()->create([
        'source_collection_id' => $source->id,
        'created_by' => $owner->id,
    ]);

    app(AttachPersonProvenance::class)->handle(
        $person,
        $source,
        $batch,
        'Synthetic person evidence.',
        'Attach the reviewed fictional person source.',
        1,
        $owner
    );
    app(AttachFamilyBranchProvenance::class)->handle(
        $branch,
        $source,
        $batch,
        'Synthetic branch evidence.',
        'Attach the reviewed fictional branch source.',
        1,
        $owner
    );

    expect($person->fresh()->metadata_revision)->toBe(2)
        ->and($person->provenanceLinks()->count())->toBe(1)
        ->and(ArchivePersonRevision::query()->where('archive_person_id', $person->id)->count())->toBe(2)
        ->and($branch->fresh()->metadata_revision)->toBe(2)
        ->and($branch->provenanceLinks()->count())->toBe(1)
        ->and(FamilyBranchRevision::query()->where('family_branch_id', $branch->id)->count())->toBe(2);
});

it('keeps people and branch surfaces owner-only', function () {
    $member = User::factory()->create([
        'role' => 'member',
        'email_verified_at' => now(),
    ]);

    foreach ([
        route('archive.people.index'),
        route('archive.branches.index'),
    ] as $url) {
        $this->actingAs($member)->get($url)->assertForbidden();
    }

    auth()->logout();
    $this->get(route('archive.people.index'))->assertRedirect('/login');
});
