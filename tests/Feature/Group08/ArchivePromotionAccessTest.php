<?php

use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaType;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

function group08AccessUpload(): IncomingUpload
{
    Storage::fake('archive_quarantine');
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_manifests');

    $bytes = 'fictional-group-08-access-photo';
    $path = 'group-08/access/fictional-photo.jpg';
    Storage::disk('archive_quarantine')->put($path, $bytes);

    return IncomingUpload::factory()->create([
        'upload_id' => 'UP-G08-ACCESS-001',
        'media_type' => MediaType::Photo,
        'incoming_path' => $path,
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'file_size_bytes' => strlen($bytes),
        'width' => 1600,
        'height' => 900,
        'sha256' => hash('sha256', $bytes),
        'perceptual_hash' => null,
        'processing_status' => IncomingProcessingStatus::Pending,
        'review_status' => IncomingReviewStatus::PendingReview,
        'duplicate_status' => DuplicateStatus::NoMatch,
        'source_file_retained' => true,
        'retained_at' => now(),
        'source_file_removed_at' => null,
        'media_item_id' => null,
    ]);
}

function group08AccessOwner(): User
{
    return User::factory()->create([
        'role' => 'owner',
        'email_verified_at' => now(),
    ]);
}

it('allows only a verified owner to review and promote', function () {
    $upload = group08AccessUpload();
    $owner = group08AccessOwner();
    $viewer = User::factory()->create([
        'role' => 'viewer',
        'email_verified_at' => now(),
    ]);

    $this->get('/admin/archive-promotions')->assertRedirect('/login');
    $this->actingAs($viewer)->get('/admin/archive-promotions')->assertForbidden();
    $this->actingAs($owner)->get('/admin/archive-promotions')
        ->assertOk()
        ->assertSee('Original-preservation boundary')
        ->assertSee($upload->upload_id);

    $this->actingAs($viewer)->post('/admin/archive-promotions/'.$upload->id)->assertForbidden();
    $this->actingAs($owner)->get('/admin/archive-promotions/'.$upload->id)
        ->assertOk()
        ->assertSee('Accept and verify original')
        ->assertSee('No original download');
});

it('adds no delete download derivative cleanup or bulk approval route', function () {
    $routeText = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri().' '.($route->getName() ?? ''))
        ->filter(fn (string $line): bool => str_contains($line, 'archive-promotions'))
        ->implode("\n");

    expect($routeText)->toContain('GET|HEAD admin/archive-promotions')
        ->and($routeText)->toContain('POST admin/archive-promotions/{incomingUpload}');

    expect(preg_match('/delete|download|derivative|cleanup|bulk|public|share/i', $routeText))->toBe(0);
});
