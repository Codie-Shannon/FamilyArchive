<?php

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('archive_quarantine');
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_manifests');
});

it('lets a trusted reviewer define freeform regions without changing the source', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/multi-photo-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('album-contact-sheet.jpg', 1200, 800);
    File::copy($photo->getRealPath(), $directory.'/album-contact-sheet.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($trusted, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $trusted, 25);
        $item = DB::table('cloud_import_items')->first();
        $source = MediaFileVersion::query()->where('version_type', 'original')->firstOrFail();
        $sourceHash = $source->sha256;
        $sourceBytes = Storage::disk($source->storage_disk)->get($source->storage_path);

        $this->withoutVite()->actingAs($trusted)
            ->get(route('intake.items.split', [$planned['session_id'], $item->id]))
            ->assertOk()
            ->assertSeeText('Separate photos from one source')
            ->assertSeeText('Original preserved');

        $proposal = PhotoSplitProposal::query()->where('cloud_import_item_id', $item->id)->firstOrFail();
        $regions = [
            ['region_id' => $proposal->regions()->firstOrFail()->region_id, 'x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'included' => true],
            ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'included' => true],
        ];
        $this->actingAs($trusted)
            ->post(route('intake.items.split.update', [$planned['session_id'], $item->id]), ['regions_json' => json_encode($regions, JSON_THROW_ON_ERROR)])
            ->assertRedirect(route('intake.items.split', [$planned['session_id'], $item->id]));

        expect($proposal->fresh()->state)->toBe('ready')
            ->and($proposal->regions()->whereNotNull('candidate_version_id')->count())->toBe(2)
            ->and($source->fresh()->sha256)->toBe($sourceHash)
            ->and(Storage::disk($source->storage_disk)->get($source->storage_path))->toBe($sourceBytes);

        $candidate = $proposal->regions()->whereNotNull('candidate_version_id')->firstOrFail();
        $this->actingAs($trusted)
            ->get(route('intake.items.split.preview', [$planned['session_id'], $item->id, $candidate->region_id]))
            ->assertOk()
            ->assertHeader('content-type', 'image/webp');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('publishes reviewed regions as independent photos with lineage to the preserved source', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/publish-split-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('four-photos.jpg', 1000, 700);
    File::copy($photo->getRealPath(), $directory.'/four-photos.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($trusted, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $trusted, 25);
        $item = DB::table('cloud_import_items')->first();

        $this->actingAs($trusted)->get(route('intake.items.split', [$planned['session_id'], $item->id]))->assertOk();
        $proposal = PhotoSplitProposal::query()->where('cloud_import_item_id', $item->id)->firstOrFail();
        $regions = [
            ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'included' => true],
            ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'included' => true],
        ];
        $this->actingAs($trusted)->post(route('intake.items.split.update', [$planned['session_id'], $item->id]), ['regions_json' => json_encode($regions, JSON_THROW_ON_ERROR)]);
        $source = MediaItem::query()->firstOrFail();
        $sourceVersion = $source->fileVersions()->where('version_type', 'original')->firstOrFail();

        $result = app(TrustedBatchReview::class)->decide($planned['session_id'], $trusted, [(int) $item->id], 'split_photos');
        $children = MediaItem::query()->whereKeyNot($source->id)->get();

        expect($result)->toBe(['reviewed' => 1, 'failed' => 0])
            ->and($source->fresh()->review_status)->toBe(MediaReviewStatus::Hidden)
            ->and($children)->toHaveCount(2)
            ->and($children->every(fn (MediaItem $child): bool => $child->review_status === MediaReviewStatus::Approved))->toBeTrue()
            ->and(MediaFileVersion::query()->whereIn('media_item_id', $children->pluck('id'))->where('parent_version_id', $sourceVersion->id)->count())->toBe(2)
            ->and($proposal->fresh()->state)->toBe('published')
            ->and(DB::table('cloud_import_items')->value('review_decision'))->toBe('split_photos');
    } finally {
        File::deleteDirectory($directory);
    }
});
