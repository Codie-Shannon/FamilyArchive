<?php

use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function group10Owner(): User
{
    return User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
}
function group10Item(User $owner, string $archiveId = 'G10-001', MediaReviewStatus $status = MediaReviewStatus::Approved): MediaItem
{
    return MediaItem::factory()->create(['archive_id' => $archiveId, 'review_status' => $status, 'approved_at' => $status === MediaReviewStatus::Approved ? now() : null, 'approved_by' => $status === MediaReviewStatus::Approved ? $owner->id : null, 'created_by' => $owner->id]);
}
function group10Original(MediaItem $item): MediaFileVersion
{
    return MediaFileVersion::factory()->create(['media_item_id' => $item->id, 'version_type' => MediaFileVersionType::Original, 'generation_status' => GenerationStatus::Ready, 'is_preferred' => true]);
}
function group10Derivative(MediaItem $item, MediaFileVersion $parent, MediaFileVersionType $type, string $bytes = 'demo-webp'): MediaFileVersion
{
    Storage::fake('archive_derivatives');
    $path = 'group10/'.$item->archive_id.'/'.$type->value.'.webp';
    Storage::disk('archive_derivatives')->put($path, $bytes);

    return MediaFileVersion::factory()->create(['media_item_id' => $item->id, 'parent_version_id' => $parent->id, 'version_type' => $type, 'storage_disk' => 'archive_derivatives', 'storage_path' => $path, 'mime_type' => 'image/webp', 'extension' => 'webp', 'file_size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'generation_status' => GenerationStatus::Ready, 'is_preferred' => true]);
}

it('denies guests and non owners', function () {
    $this->get('/archive')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]))->get('/archive')->assertForbidden();
});

it('shows only approved photos with safe thumbnail read models', function () {
    $owner = group10Owner();
    $approved = group10Item($owner);
    $original = group10Original($approved);
    group10Derivative($approved, $original, MediaFileVersionType::Thumbnail);
    group10Item($owner, 'G10-PENDING', MediaReviewStatus::PendingReview);
    $response = $this->actingAs($owner)->get('/archive');
    $response->assertOk()->assertSee('G10-001')->assertDontSee('G10-PENDING')->assertDontSee($original->storage_path)->assertDontSee($original->sha256);
});

it('paginates deterministically and never falls back to originals', function () {
    $owner = group10Owner();
    foreach (range(1, 10) as $i) {
        group10Original(group10Item($owner, sprintf('G10-%03d', $i)));
    }
    $response = $this->actingAs($owner)->get('/archive');
    $response->assertOk()->assertSee('Derivative unavailable')->assertSee('page=2')->assertDontSee('fictional-source.jpg');
});
