<?php

use App\Domain\Knowledge\Services\ArchiveKnowledgeExplorer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('retains the shared archive knowledge schema', function () {
    foreach ([
        'archive_locations',
        'archive_events',
        'family_branches',
        'archive_people',
        'family_relationships',
        'archive_event_media',
        'archive_person_media',
        'saved_archive_views',
        'curated_collections',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('filters living people sensitive locations and unreviewed records from knowledge search', function () {
    DB::table('archive_people')->insert([
        [
            'person_id' => 'PERSON-SAFE',
            'display_name' => 'Wellington Historian',
            'life_state' => 'deceased',
            'identity_state' => 'confirmed',
            'is_private' => false,
            'review_state' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'person_id' => 'PERSON-LIVING',
            'display_name' => 'Wellington Living Person',
            'life_state' => 'living',
            'identity_state' => 'confirmed',
            'is_private' => false,
            'review_state' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('archive_locations')->insert([
        [
            'location_id' => 'LOCATION-SAFE',
            'label' => 'Wellington Region',
            'precision' => 'region',
            'is_sensitive' => false,
            'review_state' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'location_id' => 'LOCATION-SENSITIVE',
            'label' => 'Wellington Private Residence',
            'precision' => 'private',
            'is_sensitive' => true,
            'review_state' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('archive_events')->insert([
        'event_id' => 'EVENT-DRAFT',
        'name' => 'Wellington Draft Event',
        'review_state' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $results = app(ArchiveKnowledgeExplorer::class)->search('Wellington');

    expect($results->pluck('stable_id')->all())
        ->toContain('PERSON-SAFE', 'LOCATION-SAFE')
        ->not->toContain('PERSON-LIVING', 'LOCATION-SENSITIVE', 'EVENT-DRAFT');
});

it('keeps archive knowledge private to a verified owner', function () {
    $this->withoutVite();

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $member = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);

    $this->get('/archive/knowledge')->assertRedirect('/login');
    $this->actingAs($member)->get('/archive/knowledge')->assertForbidden();
    $this->actingAs($owner)
        ->get('/archive/knowledge')
        ->assertOk()
        ->assertSee('Archive Knowledge')
        ->assertSee('Build Groups 13–20');
});

it('reports the Screenshot Group 01 release boundary', function () {
    expect(config('release.version'))->toBe('0.20.0')
        ->and(config('release.name'))->toBe('Archive Knowledge')
        ->and(config('release.groups'))->toBe('13-20')
        ->and(config('release.status'))->toBe('Screenshot Group 01 implemented — evidence pending');
});
