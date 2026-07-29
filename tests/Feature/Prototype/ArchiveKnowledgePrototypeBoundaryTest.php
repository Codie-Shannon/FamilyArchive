<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('retains the provisional archive knowledge schema without claiming group closure', function () {
    $tables = [
        'archive_locations', 'archive_events', 'family_branches', 'archive_people',
        'family_relationships', 'archive_event_media', 'archive_person_media',
        'saved_archive_views', 'curated_collections',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('keeps the incomplete archive knowledge prototype outside the product surface', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

    expect(config('release.archive_knowledge_prototype_enabled'))->toBeFalse()
        ->and(Route::has('archive.knowledge'))->toBeFalse();

    $this->actingAs($owner)->get('/archive/knowledge')->assertNotFound();
});

it('reports the last honestly closed release boundary', function () {
    expect(config('release.version'))->toBe('0.12.0')
        ->and(config('release.name'))->toBe('Structured Dates and Source Provenance')
        ->and(config('release.groups'))->toBe('01-12')
        ->and(config('release.status'))->toBe('Group 13 next / in development');
});
