<?php

namespace App\Domain\Processing\Services;

use App\Domain\Archive\Services\StoragePathValidator;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Derivatives\ValueObjects\WrittenDerivativeObject;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\ProcessingJobEvent;
use App\Domain\Processing\Models\ProcessingRecipe;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Models\User;
use GdImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class GdRestorationCandidateProcessor
{
    public function __construct(
        private NoOverwriteDerivativeWriter $writer,
        private StoragePathValidator $paths,
    ) {}

    public function process(ProcessingJob $job, User $actor): RestorationCandidate
    {
        if (! $actor->canManageTrustedIntake()) {
            abort(403, 'Trusted intake access is required.');
        }
        if ($job->state !== 'queued') {
            throw new DerivativeGenerationException('Only a queued restoration job can be processed.');
        }

        $source = $job->sourceVersion;
        if (! $source instanceof MediaFileVersion) {
            throw new DerivativeGenerationException('The restoration job has no immutable source.');
        }

        $sourceBytes = $this->readAndVerifySource($source);
        $sourceHash = hash('sha256', $sourceBytes);
        $job->forceFill([
            'state' => 'running',
            'attempts' => $job->attempts + 1,
            'started_at' => now(),
            'failure_reason' => null,
        ])->save();
        $this->event($job, $actor, 'processing_started');

        $written = null;
        try {
            $this->assertWithinProcessingBoundary($source);
            $recipe = ProcessingRecipe::query()->find($job->processing_recipe_id);
            if (! $recipe instanceof ProcessingRecipe || ! $recipe->is_active) {
                throw new DerivativeGenerationException('The restoration recipe is unavailable.');
            }

            $operations = $recipe->operations;
            if ($operations === []) {
                throw new DerivativeGenerationException('The restoration recipe contains no approved operations.');
            }

            $preferences = $job->automation_preferences ?? [];
            [$candidateBytes, $width, $height, $analysis, $applied] = $this->renderWithProcessingMemory(
                $sourceBytes, $source->mime_type, $operations, $preferences,
            );
            $candidateId = (string) Str::uuid();
            $path = $this->paths->validateRelativePath(
                'restoration-candidates/'.$source->media_item_id.'/'.$candidateId.'.webp',
            );
            $written = $this->writer->write($path, $candidateBytes);

            $candidate = DB::transaction(function () use (
                $job,
                $source,
                $sourceHash,
                $candidateId,
                $written,
                $width,
                $height,
                $analysis,
                $applied,
                $recipe,
                $actor,
            ): RestorationCandidate {
                $lockedJob = ProcessingJob::query()->lockForUpdate()->findOrFail($job->id);
                $lockedSource = MediaFileVersion::query()->lockForUpdate()->findOrFail($source->id);
                $this->readAndVerifySource($lockedSource);

                if ($lockedJob->state !== 'running') {
                    throw new DerivativeGenerationException('The restoration job changed while processing.');
                }

                $candidateVersion = MediaFileVersion::query()->create([
                    'media_item_id' => $lockedSource->media_item_id,
                    'parent_version_id' => $lockedSource->id,
                    'version_type' => MediaFileVersionType::EditedFull,
                    'storage_disk' => 'archive_derivatives',
                    'storage_path' => $written->relativePath,
                    'mime_type' => 'image/webp',
                    'extension' => 'webp',
                    'file_size_bytes' => $written->bytes,
                    'width' => $width,
                    'height' => $height,
                    'duration_ms' => null,
                    'sha256' => $written->sha256,
                    'perceptual_hash' => null,
                    'generation_status' => GenerationStatus::Ready,
                    'generation_recipe' => [
                        'release' => '1.8.0',
                        'recipe_id' => (string) $recipe->recipe_id,
                        'recipe_version' => (int) $recipe->version,
                        'source_sha256' => $sourceHash,
                        'operations_applied' => $applied,
                        'preserves_original' => true,
                    ],
                    'is_preferred' => false,
                ]);

                $candidate = RestorationCandidate::query()->create([
                    'candidate_id' => $candidateId,
                    'processing_job_id' => $lockedJob->id,
                    'source_version_id' => $lockedSource->id,
                    'candidate_version_id' => $candidateVersion->id,
                    'quality_checks' => [
                        'source_hash_verified_before' => true,
                        'source_hash_verified_after' => true,
                        'candidate_hash_verified' => true,
                        'separate_derivative_storage' => true,
                        'human_review_required' => true,
                    ],
                    'analysis' => $analysis,
                    'operations_applied' => $applied,
                    'review_state' => 'pending',
                ]);

                $lockedJob->forceFill([
                    'state' => 'candidate_ready',
                    'completed_at' => now(),
                ])->save();
                $this->event($lockedJob, $actor, 'candidate_ready', [
                    'candidate_id' => $candidateId,
                    'operation_count' => count($applied),
                ]);

                return $candidate;
            }, 5);

            $this->readAndVerifySource($source);

            return $candidate;
        } catch (Throwable $exception) {
            if ($written instanceof WrittenDerivativeObject) {
                $this->writer->removeCreated($written);
            }
            $job->fresh()?->forceFill([
                'state' => 'failed',
                'failure_reason' => Str::limit($exception->getMessage(), 1000, ''),
                'completed_at' => now(),
            ])->save();
            $fresh = $job->fresh();
            if ($fresh instanceof ProcessingJob) {
                $this->event($fresh, $actor, 'processing_failed');
            }

            throw $exception;
        }
    }

    private function assertWithinProcessingBoundary(MediaFileVersion $source): void
    {
        $width = (int) ($source->width ?? 0);
        $height = (int) ($source->height ?? 0);
        $maximumPixels = (int) config('archive.restoration.max_source_pixels', 45000000);
        if ($width > 0 && $height > 0 && $width > intdiv(max(1, $maximumPixels), $height)) {
            throw new DerivativeGenerationException(
                'The source exceeds the safe restoration-processing boundary and requires a lower-memory workflow.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $operations
     * @param  array<string, mixed>  $preferences
     * @return array{string, int, int, array<string, mixed>, list<string>}
     */
    private function render(string $bytes, string $mime, array $operations, array $preferences): array
    {
        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            throw new DerivativeGenerationException('The immutable source could not be decoded.');
        }

        $applied = [];
        $analysis = [
            'source_dimensions' => ['width' => imagesx($image), 'height' => imagesy($image)],
            'uploader_controls_respected' => true,
        ];

        try {
            if (isset($operations['orient']) && (bool) ($preferences['auto_rotate'] ?? true)) {
                $orientation = $this->readOrientation($bytes, $mime);
                $analysis['exif_orientation'] = $orientation;
                if ($orientation !== 1) {
                    $image = $this->applyOrientation($image, $orientation);
                    $applied[] = 'auto_rotate';
                }
            }

            if (isset($operations['deskew']) && (bool) ($preferences['deskew'] ?? true)) {
                $skew = $this->detectSkew($image);
                $analysis['deskew'] = $skew;
                if ($skew['confidence'] >= 0.55 && abs($skew['degrees']) >= 0.4 && abs($skew['degrees']) <= 8.0) {
                    $image = $this->rotate($image, -$skew['degrees']);
                    $applied[] = 'deskew';
                }
            }

            if (isset($operations['crop']) && ($preferences['crop_target'] ?? 'none') !== 'none') {
                $bounds = ($preferences['crop_target'] ?? 'none') === 'photo_edge'
                    ? $this->detectPhotoEdgeBounds($image)
                    : $this->detectContentBounds($image);
                $analysis['crop'] = $bounds;
                if ($bounds['quality_gate_passed'] && $bounds['applied']) {
                    $cropped = imagecrop($image, [
                        'x' => $bounds['x'],
                        'y' => $bounds['y'],
                        'width' => $bounds['width'],
                        'height' => $bounds['height'],
                    ]);
                    if ($cropped instanceof GdImage) {
                        $image = $cropped;
                        $applied[] = 'auto_crop';
                    }
                }
            }

            if (isset($operations['exposure']) && (bool) ($preferences['exposure'] ?? false)) {
                imagefilter($image, IMG_FILTER_BRIGHTNESS, 6);
                imagefilter($image, IMG_FILTER_CONTRAST, -4);
                $applied[] = 'gentle_exposure';
            }
            if (isset($operations['colour']) && (bool) ($preferences['color'] ?? false)) {
                imagefilter($image, IMG_FILTER_COLORIZE, 2, 0, -2);
                $applied[] = 'neutral_colour';
            }
            if (isset($operations['denoise']) && (bool) ($preferences['denoise'] ?? false)) {
                imagefilter($image, IMG_FILTER_SMOOTH, 1);
                $applied[] = 'gentle_denoise';
            }
            if (isset($operations['sharpen']) && (bool) ($preferences['sharpen'] ?? false)) {
                imageconvolution($image, [[-1, -1, -1], [-1, 16, -1], [-1, -1, -1]], 8, 0);
                $applied[] = 'gentle_sharpen';
            }
            if (isset($operations['surface_cleanup']) && (bool) ($preferences['cleanup'] ?? false)) {
                imagefilter($image, IMG_FILTER_SMOOTH, 1);
                $applied[] = 'surface_cleanup';
            }

            ob_start();
            $encoded = imagewebp($image, null, 92);
            $candidateBytes = ob_get_clean();
            if (! $encoded || $candidateBytes === '') {
                throw new DerivativeGenerationException('The restoration candidate encoder failed.');
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $analysis['candidate_dimensions'] = ['width' => $width, 'height' => $height];
            $analysis['manual_only_preferences'] = array_values(array_filter([
                ($preferences['perspective'] ?? false) ? 'perspective' : null,
                ($preferences['damage_repair'] ?? false) ? 'damage_repair' : null,
                ($preferences['upscale'] ?? false) ? 'upscale' : null,
            ]));

            return [$candidateBytes, $width, $height, $analysis, $applied];
        } finally {
            unset($image);
        }
    }

    /**
     * GD may hold several decoded canvases while rotating and cropping a camera-sized photo.
     * The scoped limit is restored immediately after rendering and never changes the source bytes.
     *
     * @param  array<string, mixed>  $operations
     * @param  array<string, mixed>  $preferences
     * @return array{string, int, int, array<string, mixed>, list<string>}
     */
    private function renderWithProcessingMemory(string $bytes, string $mime, array $operations, array $preferences): array
    {
        $originalLimit = ini_get('memory_limit');
        $processingLimit = (string) config('archive.restoration.memory_limit', '512M');

        if ($processingLimit !== '' && preg_match('/^(?:-1|\d+[KMG]?)$/i', $processingLimit)) {
            ini_set('memory_limit', $processingLimit);
        }

        try {
            return $this->render($bytes, $mime, $operations, $preferences);
        } finally {
            if ($originalLimit !== '') {
                ini_set('memory_limit', $originalLimit);
            }
        }
    }

    /** @return array<string, int|float|bool|string> */
    private function detectContentBounds(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $pixelStep = max(2, (int) ceil(max($width, $height) / 900));
        $horizontalLineStep = max($pixelStep * 6, (int) ceil($height / 84));
        $verticalLineStep = max($pixelStep * 6, (int) ceil($width / 84));
        $leftCandidates = [];
        $rightCandidates = [];
        $topCandidates = [];
        $bottomCandidates = [];
        $horizontalScans = 0;
        $verticalScans = 0;

        for ($y = $horizontalLineStep; $y < $height - $horizontalLineStep; $y += $horizontalLineStep) {
            $horizontalScans++;
            $left = $this->scanSustainedBoundary($image, $y, true, false, $pixelStep);
            $right = $this->scanSustainedBoundary($image, $y, true, true, $pixelStep);
            if ($left !== null) {
                $leftCandidates[] = $left;
            }
            if ($right !== null) {
                $rightCandidates[] = $right;
            }
        }

        for ($x = $verticalLineStep; $x < $width - $verticalLineStep; $x += $verticalLineStep) {
            $verticalScans++;
            $top = $this->scanSustainedBoundary($image, $x, false, false, $pixelStep);
            $bottom = $this->scanSustainedBoundary($image, $x, false, true, $pixelStep);
            if ($top !== null) {
                $topCandidates[] = $top;
            }
            if ($bottom !== null) {
                $bottomCandidates[] = $bottom;
            }
        }

        $left = $this->dominantBoundary($leftCandidates, $horizontalScans, $width);
        $right = $this->dominantBoundary($rightCandidates, $horizontalScans, $width);
        $top = $this->dominantBoundary($topCandidates, $verticalScans, $height);
        $bottom = $this->dominantBoundary($bottomCandidates, $verticalScans, $height);

        if ($left === null || $right === null || $top === null || $bottom === null) {
            return $this->rejectedCropBounds(
                $width,
                $height,
                'content_edge',
                'no_reliable_content_boundary',
                requiresReview: true,
            );
        }

        $paddingX = max(4, (int) round($width * 0.012));
        $paddingY = max(4, (int) round($height * 0.012));
        $x = max(0, $left['coordinate'] - $paddingX);
        $y = max(0, $top['coordinate'] - $paddingY);
        $rightEdge = min($width - 1, $right['coordinate'] + $paddingX);
        $bottomEdge = min($height - 1, $bottom['coordinate'] + $paddingY);
        $cropWidth = $rightEdge - $x + 1;
        $cropHeight = $bottomEdge - $y + 1;
        $areaRatio = ($cropWidth * $cropHeight) / ($width * $height);
        $aspectRatioDelta = $this->aspectRatioDelta($width, $height, $cropWidth, $cropHeight);
        $marginBalance = $this->marginBalance($width, $height, $x, $y, $cropWidth, $cropHeight);
        $minimumBoundaryInset = min(
            $x / max(1, $width),
            max(0, $width - ($x + $cropWidth)) / max(1, $width),
            $y / max(1, $height),
            max(0, $height - ($y + $cropHeight)) / max(1, $height),
        );
        $minimumSupport = min($left['support'], $right['support'], $top['support'], $bottom['support']);
        $averageSupport = ($left['support'] + $right['support'] + $top['support'] + $bottom['support']) / 4;
        $averageContrast = ($left['contrast'] + $right['contrast'] + $top['contrast'] + $bottom['contrast']) / 4;
        $maximumSpread = max($left['spread'], $right['spread'], $top['spread'], $bottom['spread']);
        $minimumDominance = min($left['dominance'], $right['dominance'], $top['dominance'], $bottom['dominance']);
        $maximumSecondarySupport = max(
            $left['secondary_support'],
            $right['secondary_support'],
            $top['secondary_support'],
            $bottom['secondary_support'],
        );
        $geometrySafe = $cropWidth > 100
            && $cropHeight > 100
            && $areaRatio >= 0.28
            && $areaRatio <= 0.94
            && $marginBalance <= 0.24
            && $minimumBoundaryInset >= $this->minimumCropBoundaryInset()
            && $left['coordinate'] < $right['coordinate']
            && $top['coordinate'] < $bottom['coordinate'];
        $boundarySafe = $minimumSupport >= 0.32
            && $averageSupport >= 0.60
            && $averageContrast >= 92
            && $maximumSpread <= 0.072
            && $minimumDominance >= 0.52
            && $maximumSecondarySupport <= 0.27;
        $confidence = round(min(0.97,
            0.30
            + (0.38 * min(1.0, $averageSupport / 0.72))
            + (0.19 * min(1.0, $averageContrast / 180))
            + (0.10 * max(0.0, 1 - ($maximumSpread / 0.055))),
        ), 2);
        $qualityGatePassed = $geometrySafe
            && $boundarySafe
            && $confidence >= $this->minimumCropConfidence();

        return [
            'x' => $x,
            'y' => $y,
            'width' => $cropWidth,
            'height' => $cropHeight,
            'confidence' => $confidence,
            'applied' => $qualityGatePassed,
            'quality_gate_passed' => $qualityGatePassed,
            'requires_review' => ! $qualityGatePassed,
            'method' => 'content_edge',
            'reason' => $qualityGatePassed ? 'four_sided_content_boundary_verified' : 'content_boundary_withheld',
            'area_ratio' => round($areaRatio, 4),
            'aspect_ratio_delta' => round($aspectRatioDelta, 4),
            'margin_balance' => round($marginBalance, 4),
            'minimum_boundary_inset' => round($minimumBoundaryInset, 4),
            'edge_contrast' => round($averageContrast / 765, 4),
            'minimum_boundary_support' => round($minimumSupport, 4),
            'average_boundary_support' => round($averageSupport, 4),
            'maximum_boundary_spread' => round($maximumSpread, 4),
            'minimum_boundary_dominance' => round($minimumDominance, 4),
            'maximum_secondary_support' => round($maximumSecondarySupport, 4),
        ];
    }

    /**
     * Finds the first sustained colour transition from one outer edge. Short
     * runs are ignored so album-page dots, glare and scratches are not treated
     * as the edge of a photographed print.
     *
     * @return array{coordinate: int, contrast: float}|null
     */
    private function scanSustainedBoundary(
        GdImage $image,
        int $fixedCoordinate,
        bool $horizontal,
        bool $reverse,
        int $step,
    ): ?array {
        $dimension = $horizontal ? imagesx($image) : imagesy($image);
        $start = max($step, (int) round($dimension * 0.012));
        $end = (int) round($dimension * 0.48);
        $baselineSamples = [];
        for ($offset = $start; $offset <= $start + ($step * 5); $offset += $step) {
            $coordinate = $reverse ? ($dimension - 1 - $offset) : $offset;
            $sample = $horizontal
                ? imagecolorat($image, $coordinate, $fixedCoordinate)
                : imagecolorat($image, $fixedCoordinate, $coordinate);
            if (is_int($sample)) {
                $baselineSamples[] = $sample;
            }
        }
        if ($baselineSamples === []) {
            return null;
        }
        $baseline = $this->averageRgb($baselineSamples);
        $run = [];

        for ($offset = $start + ($step * 6); $offset <= $end; $offset += $step) {
            $coordinate = $reverse ? ($dimension - 1 - $offset) : $offset;
            $color = $horizontal
                ? imagecolorat($image, $coordinate, $fixedCoordinate)
                : imagecolorat($image, $fixedCoordinate, $coordinate);
            if (! is_int($color)) {
                $run = [];

                continue;
            }
            $contrast = $this->rgbDistance($color, $baseline);
            if ($contrast >= 88) {
                $run[] = ['coordinate' => $coordinate, 'contrast' => $contrast];
                if (count($run) >= 4) {
                    $first = $run[0];

                    return ['coordinate' => $first['coordinate'], 'contrast' => array_sum(array_column($run, 'contrast')) / count($run)];
                }
            } else {
                $run = [];
            }
        }

        return null;
    }

    /**
     * @param  list<array{coordinate: int, contrast: float}>  $candidates
     * @return array{coordinate: int, support: float, contrast: float, spread: float, dominance: float, secondary_support: float}|null
     */
    private function dominantBoundary(array $candidates, int $scanCount, int $dimension): ?array
    {
        if ($candidates === [] || $scanCount === 0) {
            return null;
        }

        $tolerance = max(4, (int) round($dimension * 0.035));
        $bestCluster = [];
        foreach ($candidates as $candidate) {
            $cluster = array_values(array_filter(
                $candidates,
                static fn (array $other): bool => abs($other['coordinate'] - $candidate['coordinate']) <= $tolerance,
            ));
            if (count($cluster) > count($bestCluster)) {
                $bestCluster = $cluster;
            }
        }

        if ($bestCluster === []) {
            return null;
        }

        $coordinates = array_column($bestCluster, 'coordinate');
        sort($coordinates);
        $coordinate = $coordinates[(int) floor((count($coordinates) - 1) / 2)];
        $spread = (max($coordinates) - min($coordinates)) / max(1, $dimension);
        $remaining = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => abs($candidate['coordinate'] - $coordinate) > $tolerance,
        ));
        $secondaryCount = 0;
        foreach ($remaining as $candidate) {
            $secondaryCount = max($secondaryCount, count(array_filter(
                $remaining,
                static fn (array $other): bool => abs($other['coordinate'] - $candidate['coordinate']) <= $tolerance,
            )));
        }

        return [
            'coordinate' => $coordinate,
            'support' => count($bestCluster) / $scanCount,
            'contrast' => array_sum(array_column($bestCluster, 'contrast')) / count($bestCluster),
            'spread' => $spread,
            'dominance' => count($bestCluster) / count($candidates),
            'secondary_support' => $secondaryCount / $scanCount,
        ];
    }

    /**
     * @param  list<int>  $colors
     * @return array{0: int, 1: int, 2: int}
     */
    private function averageRgb(array $colors): array
    {
        $rgb = [0, 0, 0];
        foreach ($colors as $color) {
            $rgb[0] += ($color >> 16) & 0xFF;
            $rgb[1] += ($color >> 8) & 0xFF;
            $rgb[2] += $color & 0xFF;
        }
        $count = max(1, count($colors));

        return [
            (int) round($rgb[0] / $count),
            (int) round($rgb[1] / $count),
            (int) round($rgb[2] / $count),
        ];
    }

    /** @param array{0: int, 1: int, 2: int} $baseline */
    private function rgbDistance(int $color, array $baseline): float
    {
        return abs((($color >> 16) & 0xFF) - $baseline[0])
            + abs((($color >> 8) & 0xFF) - $baseline[1])
            + abs(($color & 0xFF) - $baseline[2]);
    }

    /**
     * Finds a photographed print only when all four dark outer edges form a
     * credible rectangle. Other captures are checked by the sustained-boundary
     * detector, whose quality gate preserves ambiguous originals for review.
     *
     * @return array<string, int|float|bool|string>
     */
    private function detectPhotoEdgeBounds(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(2, (int) ceil(max($width, $height) / 900));
        $darkLimit = 82;
        $rowScores = [];
        $columnScores = [];

        for ($y = 0; $y < $height; $y += $step) {
            $dark = 0;
            $samples = 0;
            for ($x = 0; $x < $width; $x += $step) {
                $color = imagecolorat($image, $x, $y);
                $luma = (int) round(
                    (0.2126 * (($color >> 16) & 0xFF))
                    + (0.7152 * (($color >> 8) & 0xFF))
                    + (0.0722 * ($color & 0xFF)),
                );
                $dark += $luma <= $darkLimit ? 1 : 0;
                $samples++;
            }
            $rowScores[$y] = $dark / $samples;
        }

        for ($x = 0; $x < $width; $x += $step) {
            $dark = 0;
            $samples = 0;
            for ($y = 0; $y < $height; $y += $step) {
                $color = imagecolorat($image, $x, $y);
                $luma = (int) round(
                    (0.2126 * (($color >> 16) & 0xFF))
                    + (0.7152 * (($color >> 8) & 0xFF))
                    + (0.0722 * ($color & 0xFF)),
                );
                $dark += $luma <= $darkLimit ? 1 : 0;
                $samples++;
            }
            $columnScores[$x] = $dark / $samples;
        }

        $strongRows = array_keys(array_filter($rowScores, static fn (float $score): bool => $score >= 0.48));
        $strongColumns = array_keys(array_filter($columnScores, static fn (float $score): bool => $score >= 0.48));

        if (count($strongRows) < 2 || count($strongColumns) < 2) {
            return $this->detectContentBounds($image);
        }

        $top = min($strongRows);
        $bottom = max($strongRows);
        $left = min($strongColumns);
        $right = max($strongColumns);
        $cropWidth = $right - $left + $step;
        $cropHeight = $bottom - $top + $step;
        $areaRatio = ($cropWidth * $cropHeight) / ($width * $height);
        $outerEdgeLayout = $top <= (int) ($height * 0.32)
            && $bottom >= (int) ($height * 0.68)
            && $left <= (int) ($width * 0.32)
            && $right >= (int) ($width * 0.68);
        $valid = $outerEdgeLayout
            && $cropWidth >= (int) ($width * 0.50)
            && $cropHeight >= (int) ($height * 0.50)
            && $areaRatio >= 0.45
            && $areaRatio <= 0.94;

        if (! $valid) {
            return $this->detectContentBounds($image);
        }

        $boundaryScore = (
            $rowScores[$top]
            + $rowScores[$bottom]
            + $columnScores[$left]
            + $columnScores[$right]
        ) / 4;
        $outsideScore = (
            $this->scoreNear($rowScores, max(0, $top - ($step * 3)))
            + $this->scoreNear($rowScores, min($height - 1, $bottom + ($step * 3)))
            + $this->scoreNear($columnScores, max(0, $left - ($step * 3)))
            + $this->scoreNear($columnScores, min($width - 1, $right + ($step * 3)))
        ) / 4;
        $edgeContrast = max(0.0, $boundaryScore - $outsideScore);

        $padding = max(2, $step * 2);
        $left = max(0, $left - $padding);
        $top = max(0, $top - $padding);
        $right = min($width - 1, $right + $padding);
        $bottom = min($height - 1, $bottom + $padding);

        $finalWidth = $right - $left + 1;
        $finalHeight = $bottom - $top + 1;
        $finalAreaRatio = ($finalWidth * $finalHeight) / ($width * $height);
        $aspectRatioDelta = $this->aspectRatioDelta($width, $height, $finalWidth, $finalHeight);
        $marginBalance = $this->marginBalance($width, $height, $left, $top, $finalWidth, $finalHeight);
        $boundaryStrength = min(1.0, max(0.0, ($boundaryScore - 0.48) / 0.30));
        $contrastStrength = min(1.0, $edgeContrast / 0.22);
        $confidence = round(min(0.98, 0.45 + (0.23 * $boundaryStrength) + (0.30 * $contrastStrength)), 2);
        $geometrySafe = $finalAreaRatio >= 0.45
            && $finalAreaRatio <= 0.95
            && $aspectRatioDelta <= 0.32
            && $marginBalance <= 0.28;
        $absoluteFrameEvidence = $boundaryScore >= 0.60;
        $qualityGatePassed = $geometrySafe && (
            $absoluteFrameEvidence
            || ($edgeContrast >= 0.08 && $confidence >= $this->minimumCropConfidence())
        );

        if (! $qualityGatePassed) {
            return $this->detectContentBounds($image);
        }

        $confidence = max($confidence, $absoluteFrameEvidence ? 0.78 : $confidence);

        return [
            'x' => $left,
            'y' => $top,
            'width' => $finalWidth,
            'height' => $finalHeight,
            'confidence' => $confidence,
            'applied' => true,
            'quality_gate_passed' => true,
            'requires_review' => false,
            'method' => 'photo_edge',
            'reason' => 'four_edge_frame_verified',
            'area_ratio' => round($finalAreaRatio, 4),
            'aspect_ratio_delta' => round($aspectRatioDelta, 4),
            'margin_balance' => round($marginBalance, 4),
            'edge_contrast' => round($edgeContrast, 4),
        ];
    }

    /**
     * @param  array<int, float>  $scores
     */
    private function scoreNear(array $scores, int $coordinate): float
    {
        $nearest = null;
        $distance = PHP_INT_MAX;
        foreach ($scores as $position => $score) {
            $candidateDistance = abs($position - $coordinate);
            if ($candidateDistance < $distance) {
                $nearest = $score;
                $distance = $candidateDistance;
            }
        }

        return is_float($nearest) ? $nearest : 0.0;
    }

    private function minimumCropConfidence(): float
    {
        return max(0.60, min(0.95, (float) config('archive.restoration.minimum_crop_confidence', 0.72)));
    }

    private function minimumCropBoundaryInset(): float
    {
        return max(0.0, min(0.10, (float) config('archive.restoration.minimum_crop_boundary_inset', 0.015)));
    }

    private function aspectRatioDelta(int $sourceWidth, int $sourceHeight, int $cropWidth, int $cropHeight): float
    {
        $sourceRatio = $sourceWidth / max(1, $sourceHeight);
        $cropRatio = $cropWidth / max(1, $cropHeight);

        return abs($cropRatio - $sourceRatio) / max(0.01, $sourceRatio);
    }

    private function marginBalance(
        int $sourceWidth,
        int $sourceHeight,
        int $x,
        int $y,
        int $cropWidth,
        int $cropHeight,
    ): float {
        $left = $x / max(1, $sourceWidth);
        $right = max(0, $sourceWidth - ($x + $cropWidth)) / max(1, $sourceWidth);
        $top = $y / max(1, $sourceHeight);
        $bottom = max(0, $sourceHeight - ($y + $cropHeight)) / max(1, $sourceHeight);

        return max(abs($left - $right), abs($top - $bottom));
    }

    /**
     * @return array{x: int, y: int, width: int, height: int, confidence: float, applied: bool, quality_gate_passed: bool, requires_review: bool, method: string, reason: string, area_ratio: float, aspect_ratio_delta: float, margin_balance: float, edge_contrast: float}
     */
    private function rejectedCropBounds(
        int $width,
        int $height,
        string $method,
        string $reason,
        float $confidence = 0.0,
        bool $requiresReview = false,
        float $areaRatio = 1.0,
        float $aspectRatioDelta = 0.0,
        float $marginBalance = 0.0,
        float $edgeContrast = 0.0,
    ): array {
        return [
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
            'confidence' => round($confidence, 2),
            'applied' => false,
            'quality_gate_passed' => false,
            'requires_review' => $requiresReview,
            'method' => $method,
            'reason' => $reason,
            'area_ratio' => round($areaRatio, 4),
            'aspect_ratio_delta' => round($aspectRatioDelta, 4),
            'margin_balance' => round($marginBalance, 4),
            'edge_contrast' => round($edgeContrast, 4),
        ];
    }

    /** @return array{degrees: float, confidence: float} */
    private function detectSkew(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(2, (int) ceil($height / 120));
        $points = [];
        $corner = imagecolorat($image, 2, 2);
        $bg = [($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF];

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < (int) ($width * 0.45); $x += $step) {
                $color = imagecolorat($image, $x, $y);
                $distance = abs((($color >> 16) & 0xFF) - $bg[0])
                    + abs((($color >> 8) & 0xFF) - $bg[1])
                    + abs(($color & 0xFF) - $bg[2]);
                if ($distance >= 100) {
                    $points[] = [$y, $x];
                    break;
                }
            }
        }

        if (count($points) < 12) {
            return ['degrees' => 0.0, 'confidence' => 0.0];
        }

        $meanY = array_sum(array_column($points, 0)) / count($points);
        $meanX = array_sum(array_column($points, 1)) / count($points);
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($points as [$y, $x]) {
            $numerator += ($y - $meanY) * ($x - $meanX);
            $denominator += ($y - $meanY) ** 2;
        }
        $slope = $denominator > 0 ? $numerator / $denominator : 0.0;
        $degrees = rad2deg(atan($slope));

        return [
            'degrees' => round($degrees, 2),
            'confidence' => round(min(0.9, count($points) / 100), 2),
        ];
    }

    private function readOrientation(string $bytes, string $mime): int
    {
        if (! in_array(strtolower($mime), ['image/jpeg', 'image/tiff'], true)) {
            return 1;
        }
        if (strtolower($mime) === 'image/jpeg') {
            $parsed = $this->readJpegOrientation($bytes);
            if ($parsed !== null) {
                return $parsed;
            }
        }
        if (! function_exists('exif_read_data')) {
            return 1;
        }
        $temporary = tempnam(sys_get_temp_dir(), 'fa-sg13-exif-');
        if ($temporary === false) {
            return 1;
        }
        try {
            if (file_put_contents($temporary, $bytes) === false) {
                return 1;
            }
            $exif = @exif_read_data($temporary, 'IFD0', true, false);
            $orientation = is_array($exif) ? ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1) : 1;

            $orientation = filter_var($orientation, FILTER_VALIDATE_INT);

            return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
        } finally {
            @unlink($temporary);
        }
    }

    private function readJpegOrientation(string $bytes): ?int
    {
        $exif = strpos($bytes, "Exif\0\0");
        if ($exif === false) {
            return null;
        }

        $tiff = $exif + 6;
        if (strlen($bytes) < $tiff + 8) {
            return null;
        }
        $order = substr($bytes, $tiff, 2);
        $littleEndian = $order === 'II';
        if (! $littleEndian && $order !== 'MM') {
            return null;
        }

        $short = static function (string $data, int $offset) use ($littleEndian): ?int {
            if (strlen($data) < $offset + 2) {
                return null;
            }
            $value = unpack($littleEndian ? 'v' : 'n', substr($data, $offset, 2));

            return is_array($value) ? (int) $value[1] : null;
        };
        $long = static function (string $data, int $offset) use ($littleEndian): ?int {
            if (strlen($data) < $offset + 4) {
                return null;
            }
            $value = unpack($littleEndian ? 'V' : 'N', substr($data, $offset, 4));

            return is_array($value) ? (int) $value[1] : null;
        };

        if ($short($bytes, $tiff + 2) !== 42) {
            return null;
        }
        $ifdOffset = $long($bytes, $tiff + 4);
        if ($ifdOffset === null) {
            return null;
        }
        $ifd = $tiff + $ifdOffset;
        $entryCount = $short($bytes, $ifd);
        if ($entryCount === null || $entryCount > 512) {
            return null;
        }

        for ($index = 0; $index < $entryCount; $index++) {
            $entry = $ifd + 2 + ($index * 12);
            if ($short($bytes, $entry) !== 0x0112) {
                continue;
            }
            $orientation = $short($bytes, $entry + 8);

            return $orientation !== null && $orientation >= 1 && $orientation <= 8 ? $orientation : null;
        }

        return null;
    }

    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 3) {
            $image = $this->rotate($image, 180);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        } elseif ($orientation === 5) {
            $image = $this->rotate($image, -90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 6) {
            $image = $this->rotate($image, -90);
        } elseif ($orientation === 7) {
            $image = $this->rotate($image, 90);
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 8) {
            $image = $this->rotate($image, 90);
        }

        return $image;
    }

    private function rotate(GdImage $image, float $degrees): GdImage
    {
        $background = imagecolorallocate($image, 245, 241, 229);
        $rotated = imagerotate($image, $degrees, $background === false ? 0 : $background);
        if (! $rotated instanceof GdImage) {
            throw new DerivativeGenerationException('The candidate rotation failed.');
        }

        return $rotated;
    }

    private function readAndVerifySource(MediaFileVersion $source): string
    {
        if (
            $source->version_type !== MediaFileVersionType::Original
            || $source->storage_disk !== 'archive_originals'
            || $source->generation_status !== GenerationStatus::Ready
            || ! $source->is_preferred
        ) {
            throw new DerivativeGenerationException('A ready preferred immutable original is required.');
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($source->storage_disk);
        if (! $disk->exists($source->storage_path)) {
            throw new DerivativeGenerationException('The immutable source object is missing.');
        }
        $bytes = $disk->get($source->storage_path);
        if (
            strlen($bytes) !== $source->file_size_bytes
            || ! hash_equals(strtolower($source->sha256), hash('sha256', $bytes))
        ) {
            throw new DerivativeGenerationException('The immutable source failed integrity verification.');
        }

        return $bytes;
    }

    /** @param array<string, mixed> $context */
    private function event(ProcessingJob $job, User $actor, string $event, array $context = []): void
    {
        ProcessingJobEvent::query()->create([
            'processing_job_id' => $job->id,
            'actor_id' => $actor->id,
            'event' => $event,
            'safe_context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
