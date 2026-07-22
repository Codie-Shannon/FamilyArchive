<?php

use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives;
use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/** @return array{item: MediaItem, original: MediaFileVersion} */
function group09AccessPhoto(): array
{
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_quarantine');
    Storage::fake('archive_manifests');

    $bytes = file_get_contents(base_path('tests/Fixtures/Group09/landscape-with-metadata.jpg'));
    if (! is_string($bytes)) {
        throw new RuntimeException('Group 09 access fixture missing.');
    }

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $item = MediaItem::query()->create([
        'archive_id' => 'PH_000328',
        'media_type' => MediaType::Photo,
        'title' => 'Fictional Group 09 Access Photo',
        'description' => null,
        'story' => null,
        'canonical_date' => null,
        'estimated_decade' => null,
        'date_confidence' => DateConfidence::Unknown,
        'visibility' => MediaVisibility::PrivateArchive,
        'review_status' => MediaReviewStatus::Approved,
        'sensitivity_status' => SensitivityStatus::NotFlagged,
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
        'approved_at' => now(),
    ]);
    $path = app(ArchiveStoragePath::class)->original(MediaType::Photo, $item->archive_id, 'jpg')['path'];
    Storage::disk('archive_originals')->put($path, $bytes);
    $facts = getimagesizefromstring($bytes);
    if (! is_array($facts)) {
        throw new RuntimeException('Group 09 access fixture invalid.');
    }

    $original = MediaFileVersion::query()->create([
        'media_item_id' => $item->id,
        'parent_version_id' => null,
        'version_type' => MediaFileVersionType::Original,
        'storage_disk' => 'archive_originals',
        'storage_path' => $path,
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'file_size_bytes' => strlen($bytes),
        'width' => (int) $facts[0],
        'height' => (int) $facts[1],
        'duration_ms' => null,
        'sha256' => hash('sha256', $bytes),
        'perceptual_hash' => null,
        'generation_status' => GenerationStatus::Ready,
        'generation_recipe' => null,
        'is_preferred' => true,
    ]);

    return ['item' => $item, 'original' => $original];
}

it('allows only a verified owner to generate and preview private derivatives', function () {
    $photo = group09AccessPhoto();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get('/admin/viewing-derivatives')->assertRedirect('/login');
    $this->actingAs($viewer)->get('/admin/viewing-derivatives')->assertForbidden();
    $this->actingAs($owner)->get('/admin/viewing-derivatives')
        ->assertOk()
        ->assertSee('Original-preservation boundary')
        ->assertSee($photo['item']->archive_id);

    $this->actingAs($viewer)->post('/admin/viewing-derivatives/'.$photo['item']->id)->assertForbidden();
    $this->actingAs($owner)->post('/admin/viewing-derivatives/'.$photo['item']->id)->assertRedirect();

    $versions = app(GeneratePhotoViewingDerivatives::class)->matchingExisting(
        $photo['item']->fresh(),
        $photo['original']->fresh(),
    );
    $web = $versions[MediaFileVersionType::WebDisplay->value];

    $this->actingAs($viewer)->get('/admin/viewing-derivatives/preview/'.$web->id)->assertForbidden();
    $response = $this->actingAs($owner)->get('/admin/viewing-derivatives/preview/'.$web->id);
    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->all())->not->toHaveKey('x-original-path')
        ->and($response->headers->all())->not->toHaveKey('x-original-sha256');

    $this->actingAs($owner)
        ->get('/admin/viewing-derivatives/preview/'.$photo['original']->id)
        ->assertNotFound();
});

it('adds no original download public batch delete overwrite or edited full route', function () {
    $routeText = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri().' '.($route->getName() ?? ''))
        ->filter(fn (string $line): bool => str_contains($line, 'viewing-derivatives'))
        ->implode("\n");

    expect($routeText)->toContain('GET|HEAD admin/viewing-derivatives')
        ->and($routeText)->toContain('POST admin/viewing-derivatives/{mediaItem}')
        ->and($routeText)->toContain('GET|HEAD admin/viewing-derivatives/preview/{version}')
        ->and(preg_match('/original-download|public|share|batch|delete|overwrite|edited-full/i', $routeText))->toBe(0);
});
