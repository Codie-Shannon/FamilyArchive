<?php

use App\Domain\CloudImport\Services\CloudImportPlanner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('plans sanitized mixed-media imports without bypassing preflight', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $session = app(CloudImportPlanner::class)->plan($owner, 'google_photos', [
        ['external_id' => 'g-photo-1', 'media_type' => 'photo', 'original_name' => '../Fictional Photo.jpg'],
        ['external_id' => 'g-video-1', 'media_type' => 'video', 'original_name' => 'Fictional Clip.mp4'],
        ['external_id' => 'g-audio-1', 'media_type' => 'audio', 'original_name' => 'Fictional Story.m4a'],
        ['external_id' => 'g-doc-1', 'media_type' => 'document', 'original_name' => 'Fictional Letter.pdf'],
    ]);

    expect(DB::table('cloud_import_sessions')->where('session_id', $session)->value('state'))->toBe('preflight')
        ->and(DB::table('cloud_import_items')->count())->toBe(4)
        ->and(DB::table('cloud_import_items')->where('external_id', 'g-photo-1')->value('original_name'))->toBe('Fictional Photo.jpg');
});

it('keeps apple native access unvalidated and document OCR disabled by default', function () {
    $readiness = app(CloudImportPlanner::class)->readiness();
    expect($readiness['apple_photos'])->toBeFalse()
        ->and($readiness['apple_mode'])->toBe('manual_export')
        ->and($readiness['document_ocr'])->toBeFalse();
});

it('shows the owner cloud import workspace', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.cloud-imports'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.cloud-imports'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.cloud-imports'))
        ->assertOk()
        ->assertSee('Media and cloud import')
        ->assertSee('Credentials required')
        ->assertSee('Native connector remains unvalidated')
        ->assertSee('Document OCR')
        ->assertSee('Excluded')
        ->assertDontSee('GOOGLE_PHOTOS_CLIENT_SECRET')
        ->assertDontSee('external_id');
});

it('keeps v1.2 release metadata aligned', function () {
    expect(config('release.version'))->toBe('1.2.0')
        ->and(config('release.name'))->toBe('Media & Cloud Import')
        ->and(config('release.groups'))->toBe('POST-V1-B')
        ->and(config('release.status'))->toBe('Screenshot Group 07 implemented — evidence pending');
});
