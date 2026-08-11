<?php

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

it('publishes a canonical-held source privately, replays idempotently, then makes the same crops family-visible', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
        'email' => 'canonical-split-owner@example.test',
        'email_verified_at' => now(),
    ]);
    $directory = storage_path('framework/testing/canonical-split-engine-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('canonical-contact-sheet.jpg', 1000, 700);
    File::copy($photo->getRealPath(), $directory.'/canonical-contact-sheet.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $owner, 25);
        $item = DB::table('cloud_import_items')->firstOrFail();
        $source = MediaFileVersion::query()->where('version_type', 'original')->firstOrFail();
        $sourceItem = MediaItem::query()->findOrFail($source->media_item_id);
        $sourceState = [
            'review_status' => $sourceItem->getRawOriginal('review_status'),
            'visibility' => $sourceItem->getRawOriginal('visibility'),
            'approved_by' => $sourceItem->approved_by,
            'approved_at' => $sourceItem->approved_at?->toISOString(),
        ];
        $itemState = [
            'review_decision' => $item->review_decision,
            'attention_code' => $item->attention_code,
            'reviewed_by' => $item->reviewed_by,
            'reviewed_at' => $item->reviewed_at,
        ];

        DB::table('archive_promotions')->delete();
        $engine = require base_path('tools/family_photo_split_publish.php');
        $payload = [
            'session_id' => $planned['session_id'],
            'owner_email' => $owner->email,
            'item_id' => (int) $item->id,
            'regions' => [
                ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0, 'included' => true],
                ['x' => 5000, 'y' => 0, 'width' => 5000, 'height' => 10000, 'rotation_degrees' => 0, 'included' => true],
            ],
            'evidence' => 'test-bound-crop-review',
            'make_family_visible' => false,
            'allowed_identification_ids' => [],
            'expected_source_version_id' => $source->id,
            'expected_source_sha256' => $source->sha256,
        ];

        $privateCheckpoint = $engine($payload);
        $private = $engine($payload);
        $replay = $engine($payload);
        $sourceItem->refresh();
        $itemAfterCanary = DB::table('cloud_import_items')->where('id', $item->id)->firstOrFail();

        expect($privateCheckpoint['pending_candidates'])->toBe(1)
            ->and($privateCheckpoint['outputs'])->toBe([])
            ->and($private['pending_candidates'])->toBe(0)
            ->and($private['family_visible'])->toBeFalse()
            ->and($private['outputs'])->toHaveCount(2)
            ->and($replay['outputs'])->toBe($private['outputs'])
            ->and($sourceItem->getRawOriginal('review_status'))->toBe($sourceState['review_status'])
            ->and($sourceItem->getRawOriginal('visibility'))->toBe($sourceState['visibility'])
            ->and($sourceItem->approved_by)->toBe($sourceState['approved_by'])
            ->and($sourceItem->approved_at?->toISOString())->toBe($sourceState['approved_at'])
            ->and($itemAfterCanary->review_decision)->toBe($itemState['review_decision'])
            ->and($itemAfterCanary->attention_code)->toBe($itemState['attention_code'])
            ->and($itemAfterCanary->reviewed_by)->toBe($itemState['reviewed_by'])
            ->and($itemAfterCanary->reviewed_at)->toBe($itemState['reviewed_at']);

        $payload['make_family_visible'] = true;
        $published = $engine($payload);
        $children = MediaItem::query()->whereIn('id', $published['outputs'])->get();
        User::factory()->create([
            'role' => 'viewer',
            'account_state' => 'approved',
            'email_verified_at' => now(),
        ]);
        $liveVerifier = require base_path('tools/family_photo_live_verify.php');
        $candidateOutputIds = DB::table('photo_split_regions as region')
            ->join('photo_split_proposals as proposal', 'proposal.id', '=', 'region.photo_split_proposal_id')
            ->join('cloud_import_items as ci', 'ci.id', '=', 'proposal.cloud_import_item_id')
            ->join('media_items as output', 'output.id', '=', 'region.output_media_item_id')
            ->where('ci.cloud_import_session_id', DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->value('id'))
            ->where('proposal.state', 'published')
            ->where('region.review_state', 'included')
            ->where('output.review_status', 'approved')
            ->where('output.visibility', 'family_visible')
            ->pluck('region.output_media_item_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $liveResult = $liveVerifier([
            'session_id' => $planned['session_id'],
            'after_id' => 0,
            'limit' => 100,
        ]);

        expect($published['outputs'])->toBe($private['outputs'])
            ->and($published['family_visible'])->toBeTrue()
            ->and($children)->toHaveCount(2)
            ->and($children->every(fn (MediaItem $child): bool => $child->review_status === MediaReviewStatus::Approved
                && $child->visibility === MediaVisibility::FamilyVisible))->toBeTrue()
            ->and($sourceItem->fresh()->review_status)->toBe(MediaReviewStatus::Hidden)
            ->and(PhotoSplitProposal::query()->where('cloud_import_item_id', $item->id)->value('source_version_id'))->toBe($source->id)
            ->and(DB::table('cloud_import_items')->where('id', $item->id)->value('review_decision'))->toBe('split_photos')
            ->and($candidateOutputIds)->toBe($published['outputs'])
            ->and($liveResult['ids'])->toBe($published['outputs'])
            ->and($liveResult['failed'])->toBe([])
            ->and($liveResult['verified'])->toBe($published['outputs']);
    } finally {
        File::deleteDirectory($directory);
    }
});
