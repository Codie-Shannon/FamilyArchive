<?php

use App\Domain\Archive\Models\PhotoVisibilityEvent;
use App\Domain\Archive\Models\UserArchivePreference;
use App\Domain\Archive\Services\ArchiveSelectionManager;
use App\Domain\Knowledge\Models\CuratedCollection;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->withoutVite();
});

function sg35User(string $role = 'contributor'): User
{
    return User::factory()->create(['role' => $role, 'account_state' => 'approved', 'email_verified_at' => now()]);
}

function sg35Photo(User $creator, string $title, MediaVisibility $visibility = MediaVisibility::FamilyVisible): MediaItem
{
    return MediaItem::factory()->create([
        'title' => $title, 'created_by' => $creator->id, 'visibility' => $visibility,
        'review_status' => MediaReviewStatus::Approved, 'approved_by' => $creator->id, 'approved_at' => now(),
    ]);
}

it('persists a users selection across pages and reports selected page count', function (): void {
    $user = sg35User();
    $first = sg35Photo($user, 'Page one photo');
    $second = sg35Photo($user, 'Page two photo');

    $this->actingAs($user)->putJson(route('archive.selections.update', $first), [
        'context' => 'photos:visible', 'selected' => true, 'source_page' => 1,
    ])->assertOk()->assertJson(['selected_count' => 1, 'selected_page_count' => 1]);
    $this->actingAs($user)->putJson(route('archive.selections.update', $second), [
        'context' => 'photos:visible', 'selected' => true, 'source_page' => 3,
    ])->assertOk()->assertJson(['selected_count' => 2, 'selected_page_count' => 2]);

    expect(app(ArchiveSelectionManager::class)->ids($user, 'photos:visible')->sort()->values()->all())
        ->toBe([$first->id, $second->id]);
});

it('treats repeated selection requests as an idempotent update', function (): void {
    $user = sg35User();
    $photo = sg35Photo($user, 'Repeated selection photo');

    foreach ([1, 3] as $page) {
        $this->actingAs($user)->putJson(route('archive.selections.update', $photo), [
            'context' => 'photos:visible', 'selected' => true, 'source_page' => $page,
        ])->assertOk()->assertJson(['selected_count' => 1, 'selected_page_count' => 1]);
    }

    $draft = app(ArchiveSelectionManager::class)->draft($user, 'photos:visible');
    expect(DB::table('archive_selection_items')->where('archive_selection_draft_id', $draft->id)->count())->toBe(1)
        ->and(DB::table('archive_selection_items')->where('archive_selection_draft_id', $draft->id)->value('source_page'))->toBe(3);
});

it('does not let an uploader select another users photo for hide or edit', function (): void {
    $uploader = sg35User();
    $other = sg35User();
    $photo = sg35Photo($other, 'Another uploader photo');

    $this->actingAs($uploader)->putJson(route('archive.selections.update', $photo), [
        'context' => 'photos:visible', 'selected' => true,
    ])->assertForbidden();
});

it('lets the owner select any photo while uploaders select their own', function (): void {
    $owner = sg35User('owner');
    $uploader = sg35User();
    $photo = sg35Photo($uploader, 'Owner managed photo');

    $this->actingAs($owner)->putJson(route('archive.selections.update', $photo), [
        'context' => 'photos:visible', 'selected' => true,
    ])->assertOk()->assertJson(['selected_count' => 1]);
});

it('requires a reason for single hide and restores the exact prior visibility', function (): void {
    $uploader = sg35User();
    $photo = sg35Photo($uploader, 'Branch photo', MediaVisibility::BranchVisible);

    $photo->refresh();
    $this->actingAs($uploader)->post(route('archive.photos.hide.one', $photo), [
        'reason_category' => 'privacy', 'reason_note' => 'Requested by the family.',
        'expected_metadata_revision' => $photo->metadata_revision,
    ])->assertRedirect(route('archive.index'));

    $photo->refresh();
    expect($photo->hidden_at)->not->toBeNull()
        ->and($photo->visibility)->toBe(MediaVisibility::PrivateArchive)
        ->and($photo->hidden_previous_visibility)->toBe(MediaVisibility::BranchVisible->value)
        ->and(PhotoVisibilityEvent::query()->where('media_item_id', $photo->id)->where('action', 'hide')->exists())->toBeTrue();

    app(ArchiveSelectionManager::class)->set($uploader, 'photos:hidden', $photo, true);
    $this->actingAs($uploader)->post(route('archive.photos.restore.batch'))->assertRedirect(route('archive.photos.hidden'));
    expect($photo->fresh()->visibility)->toBe(MediaVisibility::BranchVisible)
        ->and($photo->fresh()->hidden_at)->toBeNull();
});

it('batch hides selected photos without a details form and preserves album membership', function (): void {
    $owner = sg35User('owner');
    $first = sg35Photo($owner, 'First batch hide');
    $second = sg35Photo($owner, 'Second batch hide');
    $album = CuratedCollection::query()->create(['collection_id' => 'ALB-SG35', 'name' => 'Preserved membership', 'is_published' => true, 'curated_by' => $owner->id]);
    $album->mediaItems()->attach($first->id, ['added_by' => $owner->id, 'position' => 1]);
    app(ArchiveSelectionManager::class)->set($owner, 'photos:visible', $first, true);
    app(ArchiveSelectionManager::class)->set($owner, 'photos:visible', $second, true);

    $this->actingAs($owner)->post(route('archive.photos.hide.batch'))->assertRedirect(route('archive.index'));

    expect($first->fresh()->hidden_at)->not->toBeNull()
        ->and($second->fresh()->hidden_at)->not->toBeNull()
        ->and($album->mediaItems()->whereKey($first->id)->exists())->toBeTrue()
        ->and(PhotoVisibilityEvent::query()->where('batch_action', true)->where('action', 'hide')->count())->toBe(2);
});

it('keeps album selections across pages and attaches the complete server draft', function (): void {
    $trusted = sg35User('trusted_contributor');
    $first = sg35Photo($trusted, 'Album page one');
    $second = sg35Photo($trusted, 'Album page two');
    $album = CuratedCollection::query()->create(['collection_id' => 'ALB-SG35-PAGES', 'name' => 'Paged album', 'is_published' => true, 'curated_by' => $trusted->id]);
    app(ArchiveSelectionManager::class)->set($trusted, 'album:'.$album->id, $first, true, 1);
    app(ArchiveSelectionManager::class)->set($trusted, 'album:'.$album->id, $second, true, 2);

    $this->actingAs($trusted)->post(route('archive.albums.photos.attach', $album))->assertRedirect();

    expect($album->mediaItems()->pluck('media_items.id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and(app(ArchiveSelectionManager::class)->count($trusted, 'album:'.$album->id))->toBe(0);
});

it('saves row density to the user account and anchors the current result', function (): void {
    $user = sg35User();
    $this->actingAs($user)->patch(route('archive.photos.preferences.update'), [
        'rows' => 8, 'previous_rows' => 4, 'current_page' => 3,
        'return_to' => '/archive?scope=mine&page=3',
    ])->assertRedirect('/archive?scope=mine&page=2');

    expect(UserArchivePreference::query()->where('user_id', $user->id)->value('photo_gallery_rows'))->toBe(8);
});

it('returns photo detail to the exact gallery URL it was opened from', function (): void {
    $user = sg35User();
    $photo = sg35Photo($user, 'Return state photo');
    $returnTo = '/archive?scope=mine&page=7';

    $this->actingAs($user)->get(route('archive.photos.show', [$photo, 'return_to' => $returnTo]))
        ->assertOk()->assertSee('href="/archive?scope=mine&amp;page=7"', false)->assertSee('Back to photos');
});
