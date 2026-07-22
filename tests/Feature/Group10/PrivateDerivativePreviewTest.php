<?php

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('streams an authorized private derivative with safe headers', function () {
    $owner = group10Owner();
    $item = group10Item($owner);
    $original = group10Original($item);
    $web = group10Derivative($item, $original, MediaFileVersionType::WebDisplay, 'safe-pixels');
    $response = $this->actingAs($owner)->get(route('archive.derivatives.preview', $web));
    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Content-Disposition', 'inline')
        ->assertContent('safe-pixels');

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)
        ->toContain('private')
        ->toContain('no-store')
        ->toContain('max-age=0');
});

it('rejects original thumbnail misuse and unrelated derivatives', function () {
    $owner = group10Owner();
    $item = group10Item($owner);
    $original = group10Original($item);
    $this->actingAs($owner)->get(route('archive.derivatives.preview', $original))->assertNotFound();
    $bad = MediaFileVersion::factory()->create(['media_item_id' => $item->id, 'parent_version_id' => null, 'version_type' => MediaFileVersionType::WebDisplay, 'generation_status' => GenerationStatus::Ready, 'is_preferred' => true, 'storage_disk' => 'archive_derivatives', 'mime_type' => 'image/webp']);
    $this->actingAs($owner)->get(route('archive.derivatives.preview', $bad))->assertNotFound();
});
