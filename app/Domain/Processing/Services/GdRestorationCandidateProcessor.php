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
        if (! $actor->isArchiveAdministrator()) {
            abort(403, 'Archive administrator access is required.');
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
            $recipe = ProcessingRecipe::query()->find($job->processing_recipe_id);
            if (! $recipe instanceof ProcessingRecipe || ! $recipe->is_active) {
                throw new DerivativeGenerationException('The restoration recipe is unavailable.');
            }

            $operations = $recipe->operations;
            if ($operations === []) {
                throw new DerivativeGenerationException('The restoration recipe contains no approved operations.');
            }

            $preferences = $job->automation_preferences ?? [];
            [$candidateBytes, $width, $height, $analysis, $applied] = $this->render(
                $sourceBytes,
                $source->mime_type,
                $operations,
                $preferences,
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
                $bounds = $this->detectContentBounds($image);
                $analysis['crop'] = $bounds;
                if ($bounds['confidence'] >= 0.45 && $bounds['applied']) {
                    $cropped = imagecrop($image, [
                        'x' => $bounds['x'],
                        'y' => $bounds['y'],
                        'width' => $bounds['width'],
                        'height' => $bounds['height'],
                    ]);
                    if ($cropped instanceof GdImage) {
                        imagedestroy($image);
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
            imagedestroy($image);
        }
    }

    /**
     * @return array{x: int, y: int, width: int, height: int, confidence: float, applied: bool}
     */
    private function detectContentBounds(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) ceil(max($width, $height) / 700));
        $corners = [
            imagecolorat($image, 2, 2),
            imagecolorat($image, max(0, $width - 3), 2),
            imagecolorat($image, 2, max(0, $height - 3)),
            imagecolorat($image, max(0, $width - 3), max(0, $height - 3)),
        ];
        $background = [0, 0, 0];
        foreach ($corners as $color) {
            $background[0] += ($color >> 16) & 0xFF;
            $background[1] += ($color >> 8) & 0xFF;
            $background[2] += $color & 0xFF;
        }
        $background = array_map(static fn (int $value): int => (int) round($value / 4), $background);

        $minX = $width;
        $minY = $height;
        $maxX = 0;
        $maxY = 0;
        $foreground = 0;
        $sampled = 0;
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $color = imagecolorat($image, $x, $y);
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                $distance = abs($red - $background[0]) + abs($green - $background[1]) + abs($blue - $background[2]);
                $sampled++;
                if ($distance < 85) {
                    continue;
                }
                $foreground++;
                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        $ratio = $foreground / $sampled;
        $padding = max(2, $step * 2);
        $x = max(0, $minX - $padding);
        $y = max(0, $minY - $padding);
        $cropWidth = min($width - $x, ($maxX - $minX) + ($padding * 2));
        $cropHeight = min($height - $y, ($maxY - $minY) + ($padding * 2));
        $savesArea = ($cropWidth * $cropHeight) < ($width * $height * 0.96);
        $valid = $foreground > 0 && $ratio >= 0.08 && $ratio <= 0.92 && $cropWidth > 50 && $cropHeight > 50;

        if (! $valid) {
            return [
                'x' => 0,
                'y' => 0,
                'width' => $width,
                'height' => $height,
                'confidence' => 0.0,
                'applied' => false,
            ];
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => $cropWidth,
            'height' => $cropHeight,
            'confidence' => round(min(0.98, 0.45 + abs(0.5 - $ratio)), 2),
            'applied' => $savesArea,
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
        if (! in_array(strtolower($mime), ['image/jpeg', 'image/tiff'], true) || ! function_exists('exif_read_data')) {
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

            return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
        } finally {
            @unlink($temporary);
        }
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
        imagedestroy($image);

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
