<?php

use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
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

it('binds single and exclusion mutations to their exact canonical census sources', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
        'email' => 'bound-decisions-owner@example.test',
        'email_verified_at' => now(),
    ]);
    $directory = storage_path('framework/testing/bound-decision-engines-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $firstPhoto = UploadedFile::fake()->image('single-candidate.jpg', 1000, 700);
    $secondPhoto = UploadedFile::fake()->image('exclude-candidate.jpg', 900, 600);
    File::copy($firstPhoto->getRealPath(), $directory.'/single-candidate.jpg');
    File::copy($secondPhoto->getRealPath(), $directory.'/exclude-candidate.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 2);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $owner, 25);
        $items = DB::table('cloud_import_items')->orderBy('id')->get();
        expect($items)->toHaveCount(2);

        $sources = $items->mapWithKeys(function (object $item): array {
            $promotion = ArchivePromotion::query()->where('incoming_upload_id', $item->incoming_upload_id)->firstOrFail();

            return [(int) $item->id => MediaFileVersion::query()->findOrFail($promotion->original_media_file_version_id)];
        });
        $singleItem = $items[0];
        $excludeItem = $items[1];
        $singleSource = $sources[(int) $singleItem->id];
        $excludeSource = $sources[(int) $excludeItem->id];
        $proposal = PhotoSplitProposal::query()->updateOrCreate(
            ['cloud_import_item_id' => $singleItem->id],
            [
                'source_version_id' => $singleSource->id,
                'created_by' => $owner->id,
                'state' => 'suggested',
                'confidence' => 1,
                'detection_method' => 'bound-engine-test',
                'analysis' => ['detected' => true],
            ],
        );
        DB::table('cloud_import_items')->where('id', $singleItem->id)->update([
            'attention_code' => 'multiple_photos_detected',
        ]);
        $excludeSourceItem = MediaItem::query()->findOrFail($excludeSource->media_item_id);
        $excludeSourceState = [
            'review_status' => $excludeSourceItem->getRawOriginal('review_status'),
            'visibility' => $excludeSourceItem->getRawOriginal('visibility'),
        ];
        $excludeDecisionBefore = DB::table('cloud_import_items')->where('id', $excludeItem->id)->value('review_decision');

        DB::table('archive_promotions')->delete();
        $singleEngine = require base_path('tools/family_photo_single_apply.php');
        $singlePayload = [
            'session_id' => $planned['session_id'],
            'owner_email' => $owner->email,
            'item_ids' => [(int) $singleItem->id],
            'expected_sources' => [[
                'item_id' => (int) $singleItem->id,
                'source_version_id' => $singleSource->id,
                'source_sha256' => str_repeat('0', 64),
            ]],
        ];
        $singleMismatch = $singleEngine($singlePayload);
        expect($singleMismatch['done'])->toBe([])
            ->and($singleMismatch['failed'])->toHaveCount(1)
            ->and($proposal->fresh()->state)->toBe('suggested')
            ->and(DB::table('cloud_import_items')->where('id', $singleItem->id)->value('attention_code'))->toBe('multiple_photos_detected');

        $singlePayload['expected_sources'][0]['source_sha256'] = $singleSource->sha256;
        $singleApplied = $singleEngine($singlePayload);
        $singleReplay = $singleEngine($singlePayload);
        expect($singleApplied['done'])->toBe([(int) $singleItem->id])
            ->and($singleReplay['done'])->toBe([(int) $singleItem->id])
            ->and($proposal->fresh()->state)->toBe('dismissed')
            ->and(DB::table('cloud_import_items')->where('id', $singleItem->id)->value('attention_code'))->toBeNull();

        $exclusionEngine = require base_path('tools/family_photo_exclusion_apply.php');
        $excludePayload = [
            'session_id' => $planned['session_id'],
            'owner_email' => $owner->email,
            'item_ids' => [(int) $excludeItem->id],
            'expected_sources' => [[
                'item_id' => (int) $excludeItem->id,
                'source_version_id' => $excludeSource->id + 100000,
                'source_sha256' => $excludeSource->sha256,
            ]],
        ];
        $excludeMismatch = $exclusionEngine($excludePayload);
        $excludeSourceItem->refresh();
        expect($excludeMismatch['done'])->toBe([])
            ->and($excludeMismatch['failed'])->toHaveCount(1)
            ->and($excludeSourceItem->getRawOriginal('review_status'))->toBe($excludeSourceState['review_status'])
            ->and($excludeSourceItem->getRawOriginal('visibility'))->toBe($excludeSourceState['visibility'])
            ->and(DB::table('cloud_import_items')->where('id', $excludeItem->id)->value('review_decision'))->toBe($excludeDecisionBefore);

        $excludePayload['expected_sources'][0]['source_version_id'] = $excludeSource->id;
        $excluded = $exclusionEngine($excludePayload);
        $excludeReplay = $exclusionEngine($excludePayload);
        $excludeSourceItem->refresh();
        $excludedItem = DB::table('cloud_import_items')->where('id', $excludeItem->id)->firstOrFail();
        expect($excluded['done'])->toBe([(int) $excludeItem->id])
            ->and($excludeReplay['done'])->toBe([(int) $excludeItem->id])
            ->and($excludeSourceItem->review_status)->toBe(MediaReviewStatus::Hidden)
            ->and($excludeSourceItem->visibility)->toBe(MediaVisibility::PrivateArchive)
            ->and($excludedItem->review_decision)->toBe('hold')
            ->and($excludedItem->attention_code)->toBe('split_review_excluded');
    } finally {
        File::deleteDirectory($directory);
    }
});
