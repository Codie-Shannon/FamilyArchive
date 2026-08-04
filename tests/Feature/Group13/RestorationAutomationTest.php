<?php

use App\Domain\Browsing\Queries\ApprovedPhotoDetailQuery;
use App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Exceptions\RestorationProcessingBoundaryException;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\ProcessingJobEvent;
use App\Domain\Processing\Services\GdRestorationCandidateProcessor;
use App\Domain\Processing\Services\RestorationReviewService;
use App\Domain\Processing\Services\RestorationWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
});

it('turns enabled uploader choices into an approved operation recipe', function () {
    $owner = User::factory()->create(['role' => 'owner']);
    $workflow = app(RestorationWorkflow::class);
    $recipeId = $workflow->createFromPreferences('Fictional print cleanup', [
        'automation_mode' => 'candidates',
        'auto_rotate' => true,
        'deskew' => true,
        'crop_target' => 'photo_edge',
        'exposure' => true,
        'color' => false,
        'denoise' => false,
        'sharpen' => true,
        'cleanup' => false,
    ], $owner);
    $operations = json_decode((string) DB::table('processing_recipes')->where('id', $recipeId)->value('operations'), true);

    expect($operations)->toHaveKeys(['orient', 'deskew', 'crop', 'exposure', 'sharpen'])
        ->not->toHaveKeys(['colour', 'denoise', 'surface_cleanup'])
        ->and(DB::table('processing_recipes')->where('id', $recipeId)->value('automation_source'))
        ->toBe('uploader_preferences');
});

it('generates a separate cropped candidate and verifies the source before and after', function () {
    [$owner, $source] = sg13Source();
    $workflow = app(RestorationWorkflow::class);
    $preferences = sg13Preferences();
    $recipeId = $workflow->createFromPreferences('Photographed print edges', $preferences, $owner);
    $jobId = $workflow->queue($source, $recipeId, $owner, $preferences);
    $job = ProcessingJob::query()->where('job_id', $jobId)->firstOrFail();
    $before = Storage::disk('archive_originals')->get($source->storage_path);

    $candidate = app(GdRestorationCandidateProcessor::class)->process($job, $owner);
    $version = $candidate->candidateVersion;

    expect($candidate->review_state)->toBe('pending')
        ->and($candidate->operations_applied)->toContain('auto_crop')
        ->and(data_get($candidate->quality_checks, 'source_hash_verified_before'))->toBeTrue()
        ->and(data_get($candidate->quality_checks, 'source_hash_verified_after'))->toBeTrue()
        ->and($version?->version_type)->toBe(MediaFileVersionType::EditedFull)
        ->and($version?->parent_version_id)->toBe($source->id)
        ->and($version?->is_preferred)->toBeFalse()
        ->and(Storage::disk('archive_originals')->get($source->storage_path))->toBe($before)
        ->and($source->fresh()->sha256)->toBe(hash('sha256', $before))
        ->and(ProcessingJobEvent::query()->pluck('event')->all())
        ->toContain('queued', 'processing_started', 'candidate_ready');
});

it('withholds an ambiguous crop instead of treating dark subject matter as a photo frame', function () {
    [$owner, $source] = sg13Source(sg13SyntheticUnframedPortrait());
    $workflow = app(RestorationWorkflow::class);
    $preferences = sg13Preferences([
        'deskew' => false,
        'exposure' => false,
        'color' => false,
    ]);
    $recipeId = $workflow->createFromPreferences('Ambiguous unframed portrait', $preferences, $owner);
    $jobId = $workflow->queue($source, $recipeId, $owner, $preferences);

    $candidate = app(GdRestorationCandidateProcessor::class)
        ->process(ProcessingJob::query()->where('job_id', $jobId)->firstOrFail(), $owner);

    expect($candidate->operations_applied)->not->toContain('auto_crop')
        ->and(data_get($candidate->analysis, 'crop.applied'))->toBeFalse()
        ->and(data_get($candidate->analysis, 'crop.quality_gate_passed'))->toBeFalse()
        ->and(data_get($candidate->analysis, 'crop.requires_review'))->toBeTrue()
        ->and(data_get($candidate->analysis, 'crop.reason'))->toBeIn([
            'ambiguous_frame_geometry',
            'content_boundary_withheld',
            'no_reliable_content_boundary',
        ])
        ->and($candidate->candidateVersion?->width)->toBe($source->width)
        ->and($candidate->candidateVersion?->height)->toBe($source->height);
});

it('withholds an album capture when a neighbouring item enters through the image edge', function () {
    [$owner, $source] = sg13Source(sg13SyntheticAlbumWithNeighbour());
    $workflow = app(RestorationWorkflow::class);
    $preferences = sg13Preferences([
        'deskew' => false,
        'exposure' => false,
        'color' => false,
    ]);
    $recipeId = $workflow->createFromPreferences('Album page with neighbouring item', $preferences, $owner);
    $jobId = $workflow->queue($source, $recipeId, $owner, $preferences);

    $candidate = app(GdRestorationCandidateProcessor::class)
        ->process(ProcessingJob::query()->where('job_id', $jobId)->firstOrFail(), $owner);

    expect($candidate->operations_applied)->not->toContain('auto_crop')
        ->and(data_get($candidate->analysis, 'crop.applied'))->toBeFalse()
        ->and(data_get($candidate->analysis, 'crop.requires_review'))->toBeTrue()
        ->and(data_get($candidate->analysis, 'crop.method'))->toBe('content_edge')
        ->and((float) data_get($candidate->analysis, 'crop.minimum_boundary_inset'))->toBeLessThan(0.015)
        ->and($candidate->candidateVersion?->width)->toBe($source->width)
        ->and($candidate->candidateVersion?->height)->toBe($source->height);
});

it('respects disabled rotation crop and cleanup controls', function () {
    [$owner, $source] = sg13Source();
    $workflow = app(RestorationWorkflow::class);
    $recipeId = $workflow->createRecipe('Operations available but disabled', 1, [
        'orient' => ['mode' => 'exif'],
        'deskew' => ['max_degrees' => 8],
        'crop' => ['target' => 'photo_edge'],
        'exposure' => ['strength' => 'gentle'],
    ], false, $owner, 'owner');
    $preferences = sg13Preferences([
        'auto_rotate' => false,
        'deskew' => false,
        'crop_target' => 'none',
        'exposure' => false,
    ]);
    $jobId = $workflow->queue($source, $recipeId, $owner, $preferences);

    $candidate = app(GdRestorationCandidateProcessor::class)
        ->process(ProcessingJob::query()->where('job_id', $jobId)->firstOrFail(), $owner);

    expect($candidate->operations_applied)->toBe([])
        ->and(data_get($candidate->analysis, 'uploader_controls_respected'))->toBeTrue()
        ->and($candidate->candidateVersion?->width)->toBe($source->width)
        ->and($candidate->candidateVersion?->height)->toBe($source->height);
});

it('approves a candidate without replacing the preferred original', function () {
    [$owner, $source] = sg13Source();
    $workflow = app(RestorationWorkflow::class);
    $preferences = sg13Preferences();
    $recipe = $workflow->createFromPreferences('Owner-reviewed cleanup', $preferences, $owner);
    $jobId = $workflow->queue($source, $recipe, $owner, $preferences);
    $candidate = app(GdRestorationCandidateProcessor::class)
        ->process(ProcessingJob::query()->where('job_id', $jobId)->firstOrFail(), $owner);
    $originalFacts = $source->only(['storage_disk', 'storage_path', 'sha256', 'is_preferred']);

    app(RestorationReviewService::class)->decide($candidate, $owner, 'approved', 'Synthetic edges reviewed.');

    $approvedVersion = $candidate->candidateVersion()->firstOrFail();
    $item = MediaItem::query()->findOrFail($source->media_item_id);
    $web = MediaFileVersion::query()
        ->where('media_item_id', $source->media_item_id)
        ->where('version_type', MediaFileVersionType::WebDisplay)
        ->firstOrFail();
    $thumbnail = MediaFileVersion::query()
        ->where('media_item_id', $source->media_item_id)
        ->where('version_type', MediaFileVersionType::Thumbnail)
        ->firstOrFail();
    $detail = app(ApprovedPhotoDetailQuery::class)->handle($owner, $item);
    $gallery = app(ApprovedPhotoGalleryQuery::class)->handle($owner);

    expect($candidate->fresh()->review_state)->toBe('approved')
        ->and($candidate->candidateVersion?->fresh()->is_preferred)->toBeTrue()
        ->and($source->fresh()->only(['storage_disk', 'storage_path', 'sha256', 'is_preferred']))->toBe($originalFacts)
        ->and($web->parent_version_id)->toBe($approvedVersion->id)
        ->and($thumbnail->parent_version_id)->toBe($approvedVersion->id)
        ->and($web->is_preferred)->toBeTrue()
        ->and($thumbnail->is_preferred)->toBeTrue()
        ->and($web->storage_path)->toContain(substr($approvedVersion->sha256, 0, 12))
        ->and($detail?->webDisplayVersionId)->toBe($web->id)
        ->and($detail?->lineageLabel)->toBe('derived from owner-approved restoration')
        ->and($gallery->items()[0]->thumbnailVersionId)->toBe($thumbnail->id)
        ->and($candidate->job?->fresh()->state)->toBe('approved')
        ->and(ProcessingJobEvent::query()->latest('occurred_at')->value('event'))->toBe('candidate_approved');

    $this->actingAs($owner)
        ->get(route('archive.derivatives.preview', $web))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

it('fails closed when the immutable source bytes no longer match', function () {
    [$owner, $source] = sg13Source();
    $workflow = app(RestorationWorkflow::class);
    $preferences = sg13Preferences();
    $recipe = $workflow->createFromPreferences('Integrity failure example', $preferences, $owner);
    $jobId = $workflow->queue($source, $recipe, $owner, $preferences);
    Storage::disk('archive_originals')->put($source->storage_path, 'changed source bytes');
    $job = ProcessingJob::query()->where('job_id', $jobId)->firstOrFail();

    expect(fn () => app(GdRestorationCandidateProcessor::class)->process($job, $owner))
        ->toThrow(DerivativeGenerationException::class)
        ->and($job->fresh()->state)->toBe('queued')
        ->and(MediaFileVersion::query()->where('version_type', MediaFileVersionType::EditedFull)->count())->toBe(0);
});

it('routes oversized originals to the lower-memory review workflow before decoding', function () {
    [$owner, $source] = sg13Source();
    $source->forceFill(['width' => 7016, 'height' => 5100])->save();
    config()->set('archive.restoration.max_source_pixels', 24000000);

    $workflow = app(RestorationWorkflow::class);
    $preferences = sg13Preferences();
    $recipe = $workflow->createFromPreferences('Oversized source example', $preferences, $owner);
    $jobId = $workflow->queue($source, $recipe, $owner, $preferences);
    $job = ProcessingJob::query()->where('job_id', $jobId)->firstOrFail();

    expect(fn () => app(GdRestorationCandidateProcessor::class)->process($job, $owner))
        ->toThrow(RestorationProcessingBoundaryException::class)
        ->and($job->fresh()->state)->toBe('queued')
        ->and($job->fresh()->attempts)->toBe(0)
        ->and(MediaFileVersion::query()->where('version_type', MediaFileVersionType::EditedFull)->count())->toBe(0);
});

it('keeps processing review and previews inside the owner boundary', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner']);
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->get(route('admin.restoration'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.restoration'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.restoration'))
        ->assertOk()
        ->assertSee('Non-destructive restoration automation')
        ->assertSee('Uploader-controlled recipe')
        ->assertSee('SG14 boundary')
        ->assertDontSee('WASABI_SECRET_ACCESS_KEY');
});

/**
 * @return array{User, MediaFileVersion}
 */
function sg13Source(?string $sourceBytes = null): array
{
    $owner = User::factory()->create(['role' => 'owner']);
    $item = MediaItem::factory()->create([
        'archive_id' => 'PH_013013',
        'title' => 'Fictional album print',
        'review_status' => MediaReviewStatus::Approved,
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
        'approved_at' => now(),
    ]);
    $bytes = $sourceBytes ?? sg13SyntheticPrint();
    $facts = getimagesizefromstring($bytes);
    if (! is_array($facts)) {
        throw new RuntimeException('Synthetic SG13 fixture could not be decoded.');
    }
    Storage::disk('archive_originals')->put('photo/013/PH_013013.jpg', $bytes);
    $source = MediaFileVersion::query()->create([
        'media_item_id' => $item->id,
        'parent_version_id' => null,
        'version_type' => MediaFileVersionType::Original,
        'storage_disk' => 'archive_originals',
        'storage_path' => 'photo/013/PH_013013.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'file_size_bytes' => strlen($bytes),
        'width' => $facts[0],
        'height' => $facts[1],
        'sha256' => hash('sha256', $bytes),
        'generation_status' => GenerationStatus::Ready,
        'is_preferred' => true,
    ]);

    return [$owner, $source];
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function sg13Preferences(array $overrides = []): array
{
    return [
        'automation_mode' => 'candidates',
        'auto_rotate' => true,
        'deskew' => true,
        'perspective' => false,
        'crop_target' => 'photo_edge',
        'exposure' => true,
        'color' => true,
        'denoise' => false,
        'sharpen' => false,
        'cleanup' => false,
        'damage_repair' => false,
        'upscale' => false,
        'quality_warnings' => true,
        ...$overrides,
    ];
}

function sg13SyntheticPrint(): string
{
    $canvas = imagecreatetruecolor(900, 650);
    $paper = imagecolorallocate($canvas, 239, 236, 224);
    $shadow = imagecolorallocate($canvas, 36, 42, 48);
    imagefill($canvas, 0, 0, $paper);
    imagefilledrectangle($canvas, 130, 100, 790, 570, $shadow);
    imagefilledrectangle($canvas, 150, 120, 770, 550, imagecolorallocate($canvas, 50, 80, 110));
    imagefilledrectangle($canvas, 150, 390, 770, 550, imagecolorallocate($canvas, 83, 108, 63));
    imagefilledellipse($canvas, 310, 295, 150, 190, imagecolorallocate($canvas, 230, 207, 158));
    imagefilledellipse($canvas, 585, 285, 160, 200, imagecolorallocate($canvas, 218, 194, 150));
    imagefilledrectangle($canvas, 250, 385, 650, 500, imagecolorallocate($canvas, 125, 72, 55));
    imagestring($canvas, 5, 180, 510, 'FICTIONAL FAMILY PRINT', imagecolorallocate($canvas, 246, 242, 224));

    ob_start();
    imagejpeg($canvas, null, 92);
    $bytes = ob_get_clean();
    imagedestroy($canvas);

    return is_string($bytes) ? $bytes : '';
}

function sg13SyntheticUnframedPortrait(): string
{
    $canvas = imagecreatetruecolor(900, 650);
    $paper = imagecolorallocate($canvas, 239, 236, 224);
    $subject = imagecolorallocate($canvas, 36, 46, 54);
    imagefill($canvas, 0, 0, $paper);
    imagefilledellipse($canvas, 450, 325, 580, 460, $subject);
    imagefilledellipse($canvas, 365, 275, 55, 38, $paper);
    imagefilledellipse($canvas, 535, 275, 55, 38, $paper);
    imagefilledellipse($canvas, 450, 405, 210, 48, $paper);

    ob_start();
    imagejpeg($canvas, null, 92);
    $bytes = ob_get_clean();
    imagedestroy($canvas);

    return is_string($bytes) ? $bytes : '';
}

function sg13SyntheticAlbumWithNeighbour(): string
{
    $canvas = imagecreatetruecolor(900, 650);
    $album = imagecolorallocate($canvas, 236, 232, 216);
    $card = imagecolorallocate($canvas, 204, 184, 148);
    $ink = imagecolorallocate($canvas, 55, 43, 35);
    imagefill($canvas, 0, 0, $album);

    for ($x = 12; $x < 900; $x += 22) {
        for ($y = 12; $y < 650; $y += 22) {
            imageellipse($canvas, $x, $y, 5, 5, imagecolorallocate($canvas, 198, 196, 186));
        }
    }

    imagefilledrectangle($canvas, 8, 72, 690, 575, $card);
    imagerectangle($canvas, 24, 88, 674, 559, $ink);
    imagefilledrectangle($canvas, 770, 105, 899, 545, imagecolorallocate($canvas, 192, 176, 145));
    imagerectangle($canvas, 786, 121, 899, 529, $ink);
    imagestring($canvas, 5, 210, 275, 'PRIMARY ARCHIVE ITEM', $ink);

    ob_start();
    imagejpeg($canvas, null, 92);
    $bytes = ob_get_clean();
    imagedestroy($canvas);

    return is_string($bytes) ? $bytes : '';
}
