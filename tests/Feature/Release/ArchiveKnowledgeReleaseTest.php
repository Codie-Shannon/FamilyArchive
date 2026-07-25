<?php

use App\Domain\Knowledge\Services\ArchiveKnowledgeExplorer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('ships the archive knowledge schema for groups 13 through 20', function () {
    $tables = [
        'archive_locations', 'archive_events', 'family_branches', 'archive_people',
        'family_relationships', 'archive_event_media', 'archive_person_media',
        'saved_archive_views', 'curated_collections',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('searches reviewed entities without exposing sensitive locations', function () {
    DB::table('archive_locations')->insert([
        ['location_id' => 'LOC-PUBLIC', 'label' => 'Wellington', 'precision' => 'locality', 'is_sensitive' => false, 'created_at' => now(), 'updated_at' => now()],
        ['location_id' => 'LOC-PRIVATE', 'label' => 'Wellington private home', 'precision' => 'private', 'is_sensitive' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $results = app(ArchiveKnowledgeExplorer::class)->search('Wellington');

    expect($results->pluck('stable_id')->all())->toBe(['LOC-PUBLIC']);
});

it('keeps the knowledge hub private to the verified owner', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

    $this->get('/archive/knowledge')->assertRedirect('/login');
    $this->actingAs($owner)->get('/archive/knowledge')->assertOk()->assertSee('Archive Knowledge');
});

it('keeps release metadata aligned', function () {
    expect(config('release.version'))->toBe('0.20.0')
        ->and(config('release.name'))->toBe('Archive Knowledge')
        ->and(config('release.groups'))->toBe('13-20');
});
