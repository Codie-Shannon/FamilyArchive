<?php

use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
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

    expect($candidate->fresh()->review_state)->toBe('approved')
        ->and($candidate->candidateVersion?->fresh()->is_preferred)->toBeTrue()
        ->and($source->fresh()->only(['storage_disk', 'storage_path', 'sha256', 'is_preferred']))->toBe($originalFacts)
        ->and($candidate->job?->fresh()->state)->toBe('approved')
        ->and(ProcessingJobEvent::query()->latest('occurred_at')->value('event'))->toBe('candidate_approved');
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
function sg13Source(): array
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
    $bytes = sg13SyntheticPrint();
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
    $shadow = imagecolorallocate($canvas, 174, 164, 145);
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
