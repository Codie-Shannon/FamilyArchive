<?php

use App\Domain\Archive\Models\ArchivePhotoEditDraft;
use App\Domain\Archive\Services\ArchivePhotoEditor;
use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutVite();
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
});

function sg35EditorUser(string $role = 'contributor'): User
{
    return User::factory()->create(['role' => $role, 'account_state' => 'approved', 'email_verified_at' => now()]);
}

function sg35EditorPhoto(User $creator): MediaItem
{
    static $sequence = 350000;
    $sequence++;

    return MediaItem::factory()->create([
        'archive_id' => sprintf('PH_%06d', $sequence),
        'created_by' => $creator->id, 'visibility' => MediaVisibility::FamilyVisible,
        'review_status' => MediaReviewStatus::Approved, 'approved_by' => $creator->id, 'approved_at' => now(),
    ]);
}

function sg35EditorOriginal(MediaItem $item): MediaFileVersion
{
    $image = imagecreatetruecolor(80, 60);
    imagefill($image, 0, 0, imagecolorallocate($image, 90, 140, 180));
    ob_start();
    imagejpeg($image, null, 90);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);
    $path = 'group35/'.$item->archive_id.'.jpg';
    Storage::disk('archive_originals')->put($path, $bytes);

    return MediaFileVersion::factory()->create([
        'media_item_id' => $item->id, 'parent_version_id' => null,
        'version_type' => MediaFileVersionType::Original, 'storage_disk' => 'archive_originals',
        'storage_path' => $path, 'mime_type' => 'image/jpeg', 'extension' => 'jpg',
        'file_size_bytes' => strlen($bytes), 'width' => 80, 'height' => 60,
        'sha256' => hash('sha256', $bytes), 'generation_status' => GenerationStatus::Ready, 'is_preferred' => true,
    ]);
}

function sg35EditorSettings(): array
{
    return ['orient' => true, 'quarter_turn' => 1, 'straighten' => 0, 'crop_left' => 0, 'crop_top' => 0, 'crop_right' => 0, 'crop_bottom' => 0, 'brightness' => 0, 'contrast' => 0, 'red' => 0, 'green' => 0, 'blue' => 0, 'denoise' => 0, 'sharpen' => 0, 'cleanup' => 0];
}

it('keeps independent editor drafts for every selected photo', function (): void {
    $user = sg35EditorUser();
    $first = sg35EditorPhoto($user);
    $second = sg35EditorPhoto($user);
    sg35EditorOriginal($first);
    sg35EditorOriginal($second);
    app(ArchiveSelectionManager::class)->set($user, 'photos:visible', $first, true);
    app(ArchiveSelectionManager::class)->set($user, 'photos:visible', $second, true);

    $this->actingAs($user)->putJson(route('archive.photos.editor.draft', $first), sg35EditorSettings())->assertOk();
    $secondSettings = [...sg35EditorSettings(), 'quarter_turn' => -1];
    $this->actingAs($user)->putJson(route('archive.photos.editor.draft', $second), $secondSettings)->assertOk();

    expect(ArchivePhotoEditDraft::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and(ArchivePhotoEditDraft::query()->where('media_item_id', $first->id)->firstOrFail()->settings['quarter_turn'])->toBe(1)
        ->and(ArchivePhotoEditDraft::query()->where('media_item_id', $second->id)->firstOrFail()->settings['quarter_turn'])->toBe(-1);
});

it('blocks editing another uploaders photo while the owner may edit it', function (): void {
    $uploader = sg35EditorUser();
    $other = sg35EditorUser();
    $owner = sg35EditorUser('owner');
    $photo = sg35EditorPhoto($other);
    sg35EditorOriginal($photo);

    $this->actingAs($uploader)->putJson(route('archive.photos.editor.draft', $photo), sg35EditorSettings())->assertForbidden();
    $this->actingAs($owner)->putJson(route('archive.photos.editor.draft', $photo), sg35EditorSettings())->assertOk();
});

it('opens a single photo editor from detail without creating a batch selection', function (): void {
    $uploader = sg35EditorUser();
    $other = sg35EditorUser();
    $photo = sg35EditorPhoto($uploader);
    sg35EditorOriginal($photo);
    $returnTo = route('archive.photos.show', [$photo, 'return_to' => '/archive?page=3'], false);

    $this->actingAs($uploader)->get(route('archive.photos.editor', [
        'single_photo' => $photo->id, 'return_to' => $returnTo,
    ]))->assertOk()->assertSee('Return to photo')->assertSee($photo->archive_id)->assertDontSee('Save all changed');

    expect(app(ArchiveSelectionManager::class)->count($uploader, 'photos:visible'))->toBe(0);

    $this->actingAs($other)->get(route('archive.photos.editor', [
        'single_photo' => $photo->id, 'return_to' => $returnTo,
    ]))->assertForbidden();
});

it('shows the detail edit button only to the uploader or archive owner', function (): void {
    $uploader = sg35EditorUser();
    $other = sg35EditorUser();
    $owner = sg35EditorUser('owner');
    $photo = sg35EditorPhoto($uploader);
    sg35EditorOriginal($photo);
    $editUrl = route('archive.photos.editor', [
        'single_photo' => $photo->id,
        'return_to' => route('archive.photos.show', $photo, false),
    ], false);

    $this->actingAs($uploader)->get(route('archive.photos.show', $photo))
        ->assertOk()->assertSee('Edit photo')->assertSee($editUrl);
    $this->actingAs($owner)->get(route('archive.photos.show', $photo))
        ->assertOk()->assertSee('Edit photo');
    $this->actingAs($other)->get(route('archive.photos.show', $photo))
        ->assertOk()->assertDontSee('Edit photo');
});

it('publishes a non destructive edit and regenerates preferred viewing derivatives', function (): void {
    $user = sg35EditorUser();
    $photo = sg35EditorPhoto($user);
    $original = sg35EditorOriginal($photo);
    $draft = app(ArchivePhotoEditor::class)->saveDraft($photo, $user, sg35EditorSettings(), false);

    $edited = app(ArchivePhotoEditor::class)->publish($draft, $user);

    expect($original->fresh()->is_preferred)->toBeTrue()
        ->and($edited->version_type)->toBe(MediaFileVersionType::EditedFull)
        ->and($edited->is_preferred)->toBeTrue()
        ->and($edited->parent_version_id)->toBe($original->id)
        ->and(MediaFileVersion::query()->where('media_item_id', $photo->id)->where('version_type', MediaFileVersionType::WebDisplay)->where('parent_version_id', $edited->id)->where('is_preferred', true)->exists())->toBeTrue()
        ->and(MediaFileVersion::query()->where('media_item_id', $photo->id)->where('version_type', MediaFileVersionType::Thumbnail)->where('parent_version_id', $edited->id)->where('is_preferred', true)->exists())->toBeTrue();
});

it('lets a split photo restart from the full source scan without changing sibling records', function (): void {
    $owner = sg35EditorUser('owner');
    $sourceItem = sg35EditorPhoto($owner);
    $source = sg35EditorOriginal($sourceItem);
    $split = sg35EditorPhoto($owner);
    $sibling = sg35EditorPhoto($owner);
    $sessionId = DB::table('cloud_import_sessions')->insertGetId(['session_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'provider' => 'manual_export', 'state' => 'complete', 'selected_count' => 1, 'imported_count' => 1, 'failed_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $cloudItemId = DB::table('cloud_import_items')->insertGetId(['cloud_import_session_id' => $sessionId, 'external_id' => 'split-source', 'media_type' => 'photo', 'original_name' => 'source.jpg', 'state' => 'retained', 'created_at' => now(), 'updated_at' => now()]);
    $proposal = PhotoSplitProposal::query()->create(['cloud_import_item_id' => $cloudItemId, 'source_version_id' => $source->id, 'created_by' => $owner->id, 'state' => 'published', 'confidence' => 1, 'detection_method' => 'manual', 'analysis' => []]);
    PhotoSplitRegion::query()->create(['photo_split_proposal_id' => $proposal->id, 'region_id' => 'r1', 'position' => 1, 'x_basis_points' => 0, 'y_basis_points' => 0, 'width_basis_points' => 5000, 'height_basis_points' => 10000, 'rotation_degrees' => 0, 'confidence' => 1, 'source' => 'manual', 'review_state' => 'included', 'output_media_item_id' => $split->id]);
    PhotoSplitRegion::query()->create(['photo_split_proposal_id' => $proposal->id, 'region_id' => 'r2', 'position' => 2, 'x_basis_points' => 5000, 'y_basis_points' => 0, 'width_basis_points' => 5000, 'height_basis_points' => 10000, 'rotation_degrees' => 0, 'confidence' => 1, 'source' => 'manual', 'review_state' => 'included', 'output_media_item_id' => $sibling->id]);

    $draft = app(ArchivePhotoEditor::class)->saveDraft($split, $owner, sg35EditorSettings(), true);
    expect($draft->source_version_id)->toBe($source->id)
        ->and($draft->from_source_scan)->toBeTrue()
        ->and(PhotoSplitRegion::query()->where('output_media_item_id', $sibling->id)->value('region_id'))->toBe('r2');
});
