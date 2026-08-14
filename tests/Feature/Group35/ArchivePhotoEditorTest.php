<?php

use App\Domain\Archive\Models\ArchivePhotoEditBatch;
use App\Domain\Archive\Models\ArchivePhotoEditBatchItem;
use App\Domain\Archive\Models\ArchivePhotoEditDraft;
use App\Domain\Archive\Models\ArchivePhotoSplitGroup;
use App\Domain\Archive\Models\ArchivePhotoSplitMember;
use App\Domain\Archive\Services\ArchivePhotoEditBatchPublisher;
use App\Domain\Archive\Services\ArchivePhotoEditor;
use App\Domain\Archive\Services\ArchivePhotoSplitter;
use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Jobs\PublishArchivePhotoEdit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

it('queues save all immediately while keeping the selected page editor unchanged', function (): void {
    Queue::fake();
    $owner = sg35EditorUser('owner');
    $first = sg35EditorPhoto($owner);
    $second = sg35EditorPhoto($owner);
    sg35EditorOriginal($first);
    sg35EditorOriginal($second);
    app(ArchiveSelectionManager::class)->set($owner, 'photos:visible', $first, true, 4);
    app(ArchiveSelectionManager::class)->set($owner, 'photos:visible', $second, true, 4);
    app(ArchivePhotoEditor::class)->saveDraft($first, $owner, sg35EditorSettings(), false);
    app(ArchivePhotoEditor::class)->saveDraft($second, $owner, [...sg35EditorSettings(), 'quarter_turn' => -1], false);

    $response = $this->actingAs($owner)->from('/archive/photo-editor?photo='.$first->id)
        ->post(route('archive.photos.editor.publish-all'));

    $response->assertRedirect('/archive/photo-editor?photo='.$first->id)
        ->assertSessionHas('archive_photo_edit_batch_id');
    $batch = ArchivePhotoEditBatch::query()->sole();
    expect($batch->state)->toBe('queued')
        ->and($batch->total_count)->toBe(2)
        ->and($batch->items()->orderBy('position')->pluck('media_item_id')->all())->toBe([$first->id, $second->id])
        ->and(MediaFileVersion::query()->whereIn('media_item_id', [$first->id, $second->id])
            ->where('version_type', MediaFileVersionType::EditedFull)->exists())->toBeFalse();
    Queue::assertPushed(PublishArchivePhotoEdit::class, 2);
    Queue::assertPushed(PublishArchivePhotoEdit::class, fn (PublishArchivePhotoEdit $job): bool => $job->connection === 'database');

    $this->actingAs($owner)->get('/archive/photo-editor?photo='.$first->id)
        ->assertOk()
        ->assertSee('Saving changed photos in the background')
        ->assertSee('data-save-progress-label', false);

    $this->actingAs($owner)->get(route('archive.photos.editor.publish-all.status', $batch))
        ->assertOk()
        ->assertJson(['state' => 'queued', 'total' => 2, 'processed' => 0, 'active' => true]);
});

it('publishes checkpointed batch snapshots once and preserves drafts changed after queueing', function (): void {
    Queue::fake();
    $owner = sg35EditorUser('owner');
    $first = sg35EditorPhoto($owner);
    $second = sg35EditorPhoto($owner);
    sg35EditorOriginal($first);
    sg35EditorOriginal($second);
    $firstDraft = app(ArchivePhotoEditor::class)->saveDraft($first, $owner, sg35EditorSettings(), false);
    $secondDraft = app(ArchivePhotoEditor::class)->saveDraft($second, $owner, sg35EditorSettings(), false);
    $batch = app(ArchivePhotoEditBatchPublisher::class)->start($owner, ArchivePhotoEditDraft::query()->whereKey([$firstDraft->id, $secondDraft->id])->get());
    app(ArchivePhotoEditor::class)->saveDraft($second, $owner, [...sg35EditorSettings(), 'brightness' => 12], false);

    $publisher = app(ArchivePhotoEditBatchPublisher::class);
    foreach ($batch->items()->orderBy('position')->get() as $item) {
        $publisher->publish($item->id, 1);
    }
    $firstItem = $batch->items()->orderBy('position')->firstOrFail();
    $publisher->publish($firstItem->id, 2);
    $remainingDraft = ArchivePhotoEditDraft::query()->findOrFail($secondDraft->id);

    expect($batch->fresh()->state)->toBe('completed')
        ->and($batch->fresh()->completed_count)->toBe(2)
        ->and(ArchivePhotoEditDraft::query()->whereKey($firstDraft->id)->exists())->toBeFalse()
        ->and($remainingDraft->settings['brightness'])->toBe(12)
        ->and(MediaFileVersion::query()->where('media_item_id', $first->id)->where('version_type', MediaFileVersionType::EditedFull)->count())->toBe(1)
        ->and(MediaFileVersion::query()->where('media_item_id', $second->id)->where('version_type', MediaFileVersionType::EditedFull)->count())->toBe(1);
});

it('isolates failed batch photos and allows only their owner to retry them', function (): void {
    Queue::fake();
    $owner = sg35EditorUser('owner');
    $other = sg35EditorUser();
    $photo = sg35EditorPhoto($owner);
    sg35EditorOriginal($photo);
    $draft = app(ArchivePhotoEditor::class)->saveDraft($photo, $owner, sg35EditorSettings(), false);
    $batch = app(ArchivePhotoEditBatchPublisher::class)->start($owner, ArchivePhotoEditDraft::query()->whereKey($draft->id)->get());
    $photo->forceFill(['metadata_revision' => $photo->metadata_revision + 1])->save();
    $item = ArchivePhotoEditBatchItem::query()->sole();

    (new PublishArchivePhotoEdit($item->id))->handle(app(ArchivePhotoEditBatchPublisher::class));

    expect($item->fresh()->state)->toBe('failed')
        ->and($batch->fresh()->state)->toBe('completed_with_failures')
        ->and($batch->fresh()->failed_count)->toBe(1);
    $this->actingAs($other)->post(route('archive.photos.editor.publish-all.retry', $batch))->assertForbidden();
    $this->actingAs($owner)->post(route('archive.photos.editor.publish-all.retry', $batch))->assertRedirect();
    expect($item->fresh()->state)->toBe('queued');
    Queue::assertPushed(PublishArchivePhotoEdit::class, 2);
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

    $this->actingAs($owner)->get(route('archive.photos.editor', [
        'single_photo' => $split->id,
        'return_to' => route('archive.index', absolute: false),
    ]))->assertOk()->assertSee('Saved photos from this source')->assertSee($sibling->archive_id);
});

it('publishes preservation safe split photos from the selected source basis', function (): void {
    $owner = sg35EditorUser('owner');
    $photo = sg35EditorPhoto($owner);
    $photo->forceFill(['contains_living_person' => true, 'contains_child' => true])->save();
    $original = sg35EditorOriginal($photo);
    $draft = app(ArchivePhotoEditor::class)->saveDraft($photo, $owner, sg35EditorSettings(), false);
    $current = app(ArchivePhotoEditor::class)->publish($draft, $owner);

    $children = app(ArchivePhotoSplitter::class)->split($photo->fresh(), $owner, [
        ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
        ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 90],
    ], $photo->fresh()->metadata_revision, 'original');

    $group = ArchivePhotoSplitGroup::query()->with('members')->sole();
    expect($children)->toHaveCount(2)
        ->and($photo->fresh()->review_status)->toBe(MediaReviewStatus::Hidden)
        ->and($group->source_media_item_id)->toBe($photo->id)
        ->and($group->source_version_id)->toBe($original->id)
        ->and($group->source_version_id)->not->toBe($current->id)
        ->and($group->source_basis)->toBe('original')
        ->and($group->gallery_archive_id)->toBe($photo->archive_id)
        ->and($group->gallery_approved_at?->toISOString())->toBe($children[0]->approved_at?->toISOString())
        ->and($group->members)->toHaveCount(2)
        ->and((bool) $children[0]->fresh()->getAttribute('contains_living_person'))->toBeTrue()
        ->and((bool) $children[0]->fresh()->getAttribute('contains_child'))->toBeTrue()
        ->and(ArchivePhotoSplitMember::query()->where('media_item_id', $children[0]->id)->value('position'))->toBe(1);

    foreach ($children as $child) {
        $split = MediaFileVersion::query()->where('media_item_id', $child->id)
            ->where('version_type', MediaFileVersionType::EditedFull)->sole();
        expect($split->parent_version_id)->toBe($original->id)
            ->and(data_get($split->generation_recipe, 'operation'))->toBe('archive_photo_split')
            ->and(MediaFileVersion::query()->where('media_item_id', $child->id)->where('version_type', MediaFileVersionType::Thumbnail)->exists())->toBeTrue();
    }
});

it('groups every split sibling beneath its source only inside edit mode', function (): void {
    $owner = sg35EditorUser('owner');
    $photo = sg35EditorPhoto($owner);
    sg35EditorOriginal($photo);
    $children = app(ArchivePhotoSplitter::class)->split($photo, $owner, [
        ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
        ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
    ], (int) ($photo->metadata_revision ?? 0), 'current');

    $editor = $this->actingAs($owner)->get(route('archive.photos.editor', [
        'single_photo' => $children[0]->id,
        'return_to' => route('archive.index', absolute: false),
    ]));
    $editor->assertOk()
        ->assertSee('Saved photos from this source')
        ->assertSee($children[0]->archive_id)
        ->assertSee($children[1]->archive_id)
        ->assertSee('familyarchive:editor-filmstrip', false)
        ->assertSee('Split photo');

    $this->actingAs($owner)->get(route('archive.photos.show', $children[0]))
        ->assertOk()
        ->assertDontSee('Saved photos from this source')
        ->assertDontSee($children[1]->archive_id);
});

it('keeps split children out of the batch selector and previews the original source', function (): void {
    $owner = sg35EditorUser('owner');
    $source = sg35EditorPhoto($owner);
    $other = sg35EditorPhoto($owner);
    sg35EditorOriginal($source);
    sg35EditorOriginal($other);
    app(ArchiveSelectionManager::class)->set($owner, 'photos:visible', $source, true);
    app(ArchiveSelectionManager::class)->set($owner, 'photos:visible', $other, true);
    $children = app(ArchivePhotoSplitter::class)->split($source, $owner, [
        ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
        ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
    ], (int) ($source->metadata_revision ?? 0), 'current');

    $preview = $this->actingAs($owner)->get(route('archive.photos.editor', [
        'photo' => $source->id,
        'return_to' => '/archive?page=4',
    ]));
    $preview->assertOk()
        ->assertSee('Original preview only')
        ->assertSee('data-batch-photo-id="'.$source->id.'"', false)
        ->assertSee('data-batch-photo-id="'.$other->id.'"', false)
        ->assertDontSee('data-batch-photo-id="'.$children[0]->id.'"', false)
        ->assertSee('data-split-photo-id="'.$children[0]->id.'"', false)
        ->assertSee('data-split-photo-id="'.$children[1]->id.'"', false);

    $editor = $this->actingAs($owner)->get(route('archive.photos.editor', [
        'photo' => $source->id,
        'split_photo' => $children[1]->id,
        'return_to' => '/archive?page=4',
    ]));
    $editor->assertOk()
        ->assertDontSee('Original preview only')
        ->assertSee(route('archive.photos.editor.draft', $children[1]))
        ->assertSee('xl:sticky xl:top-3', false);

    $this->actingAs($owner)->get(route('archive.photos.editor.thumbnail', [$source, 'basis' => 'original']))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('returns a published split to its batch group with the first split selected', function (): void {
    $owner = sg35EditorUser('owner');
    $source = sg35EditorPhoto($owner);
    sg35EditorOriginal($source);
    app(ArchiveSelectionManager::class)->set($owner, 'photos:visible', $source, true);

    $response = $this->actingAs($owner)->post(route('archive.photos.editor.split.publish', $source), [
        'expected_metadata_revision' => (int) ($source->metadata_revision ?? 0),
        'source_basis' => 'current',
        'regions_json' => json_encode([
            ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
            ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
        ], JSON_THROW_ON_ERROR),
        'return_to' => '/archive?page=6',
        'editor_return_to' => '/archive/photo-editor?photo='.$source->id.'&return_to=%2Farchive%3Fpage%3D6',
    ]);

    $response->assertSessionHasNoErrors();
    $first = ArchivePhotoSplitMember::query()->orderBy('position')->firstOrFail();
    $response->assertRedirect('/archive/photo-editor?photo='.$source->id.'&return_to=%2Farchive%3Fpage%3D6&split_photo='.$first->media_item_id);
});

it('publishes the current draft before splitting without touching other batch drafts', function (): void {
    $owner = sg35EditorUser('owner');
    $source = sg35EditorPhoto($owner);
    $other = sg35EditorPhoto($owner);
    sg35EditorOriginal($source);
    sg35EditorOriginal($other);
    app(ArchivePhotoEditor::class)->saveDraft($source, $owner, sg35EditorSettings(), false);
    app(ArchivePhotoEditor::class)->saveDraft($other, $owner, [...sg35EditorSettings(), 'quarter_turn' => -1], false);

    $this->actingAs($owner)->postJson(route('archive.photos.editor.publish', $source))
        ->assertOk()
        ->assertJson(['published' => true]);

    expect(ArchivePhotoEditDraft::query()->where('media_item_id', $source->id)->exists())->toBeFalse()
        ->and(ArchivePhotoEditDraft::query()->where('media_item_id', $other->id)->exists())->toBeTrue()
        ->and(MediaFileVersion::query()->where('media_item_id', $source->id)
            ->where('version_type', MediaFileVersionType::EditedFull)
            ->where('is_preferred', true)->exists())->toBeTrue();

    $this->actingAs($owner)->get(route('archive.photos.editor.split', $source))
        ->assertOk()
        ->assertSee('Current corrected version');
});

it('keeps published splits together at the original gallery position', function (): void {
    $owner = sg35EditorUser('owner');
    $newer = sg35EditorPhoto($owner);
    $source = sg35EditorPhoto($owner);
    $older = sg35EditorPhoto($owner);
    $newer->forceFill(['approved_at' => now()->subHour()])->save();
    $source->forceFill(['approved_at' => now()->subHours(2)])->save();
    $older->forceFill(['approved_at' => now()->subHours(3)])->save();
    sg35EditorOriginal($newer);
    sg35EditorOriginal($source);
    sg35EditorOriginal($older);

    $children = app(ArchivePhotoSplitter::class)->split($source, $owner, [
        ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
        ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
    ], (int) ($source->metadata_revision ?? 0), 'current');

    $ids = collect(app(ApprovedPhotoGalleryQuery::class)->handle($owner, 10)->items())
        ->map(fn ($item): int => $item->mediaItemId)
        ->all();

    expect($ids)->toBe([$newer->id, $children[0]->id, $children[1]->id, $older->id]);
});

it('saves edits made to an archive split without a server error', function (): void {
    $owner = sg35EditorUser('owner');
    $source = sg35EditorPhoto($owner);
    sg35EditorOriginal($source);
    $children = app(ArchivePhotoSplitter::class)->split($source, $owner, [
        ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
        ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0],
    ], (int) ($source->metadata_revision ?? 0), 'current');
    $draft = app(ArchivePhotoEditor::class)->saveDraft($children[0], $owner, sg35EditorSettings(), false);

    $edited = app(ArchivePhotoEditor::class)->publish($draft, $owner);

    expect($edited->parent_version_id)->not->toBeNull()
        ->and($edited->media_item_id)->toBe($children[0]->id)
        ->and(MediaFileVersion::query()->where('media_item_id', $children[0]->id)
            ->where('version_type', MediaFileVersionType::Thumbnail)
            ->where('parent_version_id', $edited->id)
            ->where('is_preferred', true)->exists())->toBeTrue();
});

it('opens the split workspace with full source duplicates and current or original choice', function (): void {
    $owner = sg35EditorUser('owner');
    $photo = sg35EditorPhoto($owner);
    sg35EditorOriginal($photo);

    $this->actingAs($owner)->get(route('archive.photos.editor.split', $photo))
        ->assertOk()
        ->assertSee('Current corrected version')
        ->assertSee('Preserved original')
        ->assertSee('Number of photos')
        ->assertSee('regions.push({x:0,y:0,width:10000,height:10000,rotation_degrees:0})', false);
});

it('drains newer editor revisions and prepares the current draft before opening split', function (): void {
    $owner = sg35EditorUser('owner');
    $photo = sg35EditorPhoto($owner);
    sg35EditorOriginal($photo);

    $this->actingAs($owner)->get(route('archive.photos.editor', [
        'single_photo' => $photo->id,
        'return_to' => route('archive.index', absolute: false),
    ]))->assertOk()
        ->assertSee('data-prepare-split', false)
        ->assertSee('savedRevision<changeRevision', false)
        ->assertSee('while(hasUnsaved())', false)
        ->assertSee('publishDraft', false);
});
