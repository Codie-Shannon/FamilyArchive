<?php

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
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

it('approves a canonical-held original with verified derivatives and replays safely', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
        'email' => 'canonical-original-owner@example.test',
        'email_verified_at' => now(),
    ]);
    $directory = storage_path('framework/testing/canonical-original-engine-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('canonical-single.jpg', 1000, 700);
    File::copy($photo->getRealPath(), $directory.'/canonical-single.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $owner, 25);
        $item = DB::table('cloud_import_items')->firstOrFail();
        DB::table('cloud_import_items')->where('id', $item->id)->update([
            'review_decision' => 'hold',
            'attention_code' => 'exact_duplicate',
        ]);
        $source = MediaFileVersion::query()->where('version_type', MediaFileVersionType::Original)->firstOrFail();
        $sourceItem = MediaItem::query()->findOrFail($source->media_item_id);

        DB::table('archive_promotions')->delete();
        $engine = require base_path('tools/family_photo_original_approve.php');
        $payload = [
            'session_id' => $planned['session_id'],
            'owner_email' => $owner->email,
            'item_ids' => [(int) $item->id],
            'allowed_identification_ids' => [],
            'maximum_source_pixels' => 80000000,
            'expected_sources' => [[
                'item_id' => (int) $item->id,
                'source_version_id' => $source->id,
                'source_sha256' => $source->sha256,
            ]],
        ];

        $approved = $engine($payload);
        $replay = $engine($payload);
        $sourceItem->refresh();
        $itemAfter = DB::table('cloud_import_items')->where('id', $item->id)->firstOrFail();
        $versions = MediaFileVersion::query()
            ->where('media_item_id', $sourceItem->id)
            ->where('parent_version_id', $source->id)
            ->whereIn('version_type', [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail])
            ->get();
        User::factory()->create([
            'role' => 'viewer',
            'account_state' => 'approved',
            'email_verified_at' => now(),
        ]);
        $liveVerifier = require base_path('tools/family_photo_live_verify.php');
        $liveResult = $liveVerifier([
            'session_id' => $planned['session_id'],
            'after_id' => 0,
            'limit' => 100,
        ]);

        expect($approved)->toMatchArray([
            'approved' => [(int) $item->id],
            'failed' => [],
            'skipped' => [],
        ])->and($replay)->toMatchArray([
            'approved' => [],
            'failed' => [],
            'skipped' => [(int) $item->id],
        ])->and($sourceItem->review_status)->toBe(MediaReviewStatus::Approved)
            ->and($sourceItem->visibility)->toBe(MediaVisibility::FamilyVisible)
            ->and($sourceItem->approved_by)->toBe($owner->id)
            ->and($itemAfter->review_decision)->toBe('original')
            ->and($itemAfter->attention_code)->toBeNull()
            ->and($versions)->toHaveCount(2)
            ->and($versions->every(fn (MediaFileVersion $version): bool => $version->generation_status === GenerationStatus::Ready
                && $version->is_preferred
                && Storage::disk('archive_derivatives')->exists($version->storage_path)
                && hash_equals(strtolower($version->sha256), hash('sha256', Storage::disk('archive_derivatives')->get($version->storage_path)))))->toBeTrue()
            ->and($liveResult['ids'])->toBe([$sourceItem->id])
            ->and($liveResult['verified'])->toBe([$sourceItem->id])
            ->and($liveResult['failed'])->toBe([]);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('publishes a reviewed whole-photo rotation as an approved non-destructive viewing source', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
        'email' => 'rotated-original-owner@example.test',
        'email_verified_at' => now(),
    ]);
    $directory = storage_path('framework/testing/rotated-original-engine-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('sideways-single.jpg', 1000, 700);
    File::copy($photo->getRealPath(), $directory.'/sideways-single.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $owner, 25);
        $item = DB::table('cloud_import_items')->firstOrFail();
        DB::table('cloud_import_items')->where('id', $item->id)->update([
            'review_decision' => 'hold',
            'attention_code' => null,
        ]);
        $source = MediaFileVersion::query()->where('version_type', MediaFileVersionType::Original)->firstOrFail();
        $sourceBytes = Storage::disk('archive_originals')->get($source->storage_path);
        $engine = require base_path('tools/family_photo_original_approve.php');
        $payload = [
            'session_id' => $planned['session_id'],
            'owner_email' => $owner->email,
            'item_ids' => [(int) $item->id],
            'allowed_identification_ids' => [],
            'maximum_source_pixels' => 80000000,
            'expected_sources' => [[
                'item_id' => (int) $item->id,
                'source_version_id' => $source->id,
                'source_sha256' => $source->sha256,
                'rotation_degrees' => 90,
            ]],
        ];

        $approved = $engine($payload);
        $edited = MediaFileVersion::query()
            ->where('media_item_id', $source->media_item_id)
            ->where('parent_version_id', $source->id)
            ->where('version_type', MediaFileVersionType::EditedFull)
            ->get()
            ->first(fn (MediaFileVersion $version): bool => data_get($version->generation_recipe, 'operation') === 'family_photo_single_rotation');
        expect($edited)->not->toBeNull();
        $viewing = MediaFileVersion::query()
            ->where('media_item_id', $source->media_item_id)
            ->where('parent_version_id', $edited->id)
            ->whereIn('version_type', [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail])
            ->get();
        User::factory()->create([
            'role' => 'viewer',
            'account_state' => 'approved',
            'email_verified_at' => now(),
        ]);
        $liveVerifier = require base_path('tools/family_photo_live_verify.php');
        $liveResult = $liveVerifier([
            'session_id' => $planned['session_id'],
            'after_id' => 0,
            'limit' => 100,
            'expected_rotations' => [[
                'item_id' => (int) $item->id,
                'rotation_degrees' => 90,
                'source_sha256' => $source->sha256,
            ]],
        ]);

        expect($approved)->toMatchArray([
            'approved' => [(int) $item->id],
            'failed' => [],
            'skipped' => [],
        ])->and($edited->generation_recipe['operation'])->toBe('family_photo_single_rotation')
            ->and($edited->generation_recipe['clockwise_degrees'])->toBe(90)
            ->and($edited->width)->toBe(700)
            ->and($edited->height)->toBe(1000)
            ->and($edited->is_preferred)->toBeTrue()
            ->and($edited->restorationCandidate?->review_state)->toBe('approved')
            ->and($viewing)->toHaveCount(2)
            ->and($viewing->every(fn (MediaFileVersion $version): bool => $version->is_preferred
                && Storage::disk('archive_derivatives')->exists($version->storage_path)))->toBeTrue()
            ->and(Storage::disk('archive_originals')->get($source->storage_path))->toBe($sourceBytes)
            ->and(hash('sha256', $sourceBytes))->toBe(strtolower($source->sha256))
            ->and($liveResult['verified_rotations'])->toBe([(int) $item->id])
            ->and($liveResult['failed'])->toBe([]);
    } finally {
        File::deleteDirectory($directory);
    }
});
