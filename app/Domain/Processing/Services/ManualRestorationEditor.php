<?php

namespace App\Domain\Processing\Services;

use App\Domain\Archive\Services\StoragePathValidator;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Intake\Models\IncomingUpload;
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
use Illuminate\Validation\ValidationException;
use Throwable;

final class ManualRestorationEditor
{
    public function __construct(
        private NoOverwriteDerivativeWriter $writer,
        private StoragePathValidator $paths,
    ) {}

    /**
     * Reuse the proven intake renderer for an already-approved archive source.
     *
     * @param  array<string, bool|float|int>  $settings
     * @return array{bytes: string, width: int, height: int, operations: array<string, mixed>, normalized: array<string, bool|float|int>, source_sha256: string}
     */
    public function renderApprovedSource(MediaFileVersion $source, array $settings): array
    {
        $normalized = $this->normalize($settings);
        $sourceBytes = $this->readAndVerifyApprovedSource($source);
        [$bytes, $width, $height, $operations] = $this->renderWithProcessingMemory(
            $sourceBytes,
            $source->mime_type,
            $normalized,
        );
        $this->readAndVerifyApprovedSource($source);

        return compact('bytes', 'width', 'height', 'operations', 'normalized') + [
            'source_sha256' => hash('sha256', $sourceBytes),
        ];
    }

    /**
     * @param  array<string, bool|float|int>  $settings
     */
    public function save(object $session, int $itemId, User $actor, array $settings): RestorationCandidate
    {
        abort_unless($actor->canManageTrustedIntake(), 403, 'Trusted intake access is required.');

        $item = DB::table('cloud_import_items')
            ->where('id', $itemId)
            ->where('cloud_import_session_id', data_get($session, 'id'))
            ->where('state', 'retained')
            ->first();
        abort_unless($item !== null, 404);

        if (data_get($item, 'review_decision') !== null) {
            throw ValidationException::withMessages(['item' => 'Reviewed items cannot be changed.']);
        }

        $currentId = data_get($item, 'restoration_candidate_id');
        $current = $currentId === null ? null : RestorationCandidate::query()
            ->with(['sourceVersion', 'job'])
            ->whereKey((int) $currentId)
            ->first();
        if ($current instanceof RestorationCandidate && $current->review_state !== 'pending') {
            throw ValidationException::withMessages(['item' => 'The current review version can no longer be edited.']);
        }

        $source = $current->sourceVersion ?? $this->originalSourceForItem($item);
        if (! $source instanceof MediaFileVersion) {
            throw new DerivativeGenerationException('The manual editor has no immutable source.');
        }

        $normalized = $this->normalize($settings);
        $sourceBytes = $this->readAndVerifySource($source);
        [$candidateBytes, $width, $height, $operations] = $this->renderWithProcessingMemory(
            $sourceBytes,
            $source->mime_type,
            $normalized,
        );

        $candidateId = (string) Str::uuid();
        $path = $this->paths->validateRelativePath(
            'restoration-candidates/'.$source->media_item_id.'/'.$candidateId.'.webp',
        );
        $written = $this->writer->write($path, $candidateBytes);

        try {
            $candidate = DB::transaction(function () use (
                $session,
                $itemId,
                $actor,
                $current,
                $source,
                $sourceBytes,
                $normalized,
                $operations,
                $candidateId,
                $written,
                $width,
                $height,
            ): RestorationCandidate {
                $lockedItem = DB::table('cloud_import_items')
                    ->where('id', $itemId)
                    ->where('cloud_import_session_id', data_get($session, 'id'))
                    ->lockForUpdate()
                    ->first();
                abort_unless($lockedItem !== null, 404);
                if (data_get($lockedItem, 'review_decision') !== null) {
                    throw ValidationException::withMessages(['item' => 'This item was reviewed while the editor was open.']);
                }

                $lockedCandidateId = data_get($lockedItem, 'restoration_candidate_id');
                if (($lockedCandidateId === null ? null : (int) $lockedCandidateId) !== $current?->id) {
                    throw ValidationException::withMessages(['item' => 'The review version changed while the editor was open. Reload it and try again.']);
                }

                $lockedCurrent = $current instanceof RestorationCandidate
                    ? RestorationCandidate::query()->lockForUpdate()->findOrFail($current->id)
                    : null;
                if ($lockedCurrent instanceof RestorationCandidate && $lockedCurrent->review_state !== 'pending') {
                    throw ValidationException::withMessages(['item' => 'The review version changed while the editor was open. Reload it and try again.']);
                }

                $lockedSource = MediaFileVersion::query()->lockForUpdate()->findOrFail($source->id);
                $this->readAndVerifySource($lockedSource);

                $recipe = ProcessingRecipe::query()->create([
                    'created_by' => $actor->id,
                    'recipe_id' => 'RCP-'.strtoupper(Str::random(12)),
                    'name' => 'Manual image adjustment '.$candidateId,
                    'version' => 1,
                    'operations' => $operations,
                    'automation_source' => 'manual_editor',
                    'is_batch_profile' => false,
                    'is_active' => true,
                ]);
                $job = ProcessingJob::query()->create([
                    'job_id' => (string) Str::uuid(),
                    'media_item_id' => $lockedSource->media_item_id,
                    'source_version_id' => $lockedSource->id,
                    'processing_recipe_id' => $recipe->id,
                    'requested_by' => $actor->id,
                    'automation_preferences' => ['mode' => 'manual', 'settings' => $normalized],
                    'state' => 'candidate_ready',
                    'attempts' => 1,
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);
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
                        'editor' => 'manual',
                        'source_sha256' => hash('sha256', $sourceBytes),
                        'operations' => $operations,
                        'preserves_original' => true,
                    ],
                    'is_preferred' => false,
                ]);
                $candidate = RestorationCandidate::query()->create([
                    'candidate_id' => $candidateId,
                    'processing_job_id' => $job->id,
                    'source_version_id' => $lockedSource->id,
                    'candidate_version_id' => $candidateVersion->id,
                    'quality_checks' => [
                        'source_hash_verified_before' => true,
                        'source_hash_verified_after' => true,
                        'candidate_hash_verified' => true,
                        'separate_derivative_storage' => true,
                        'human_review_required' => true,
                        'manual_adjustment' => true,
                    ],
                    'analysis' => [
                        'editor' => 'manual',
                        'source_dimensions' => ['width' => $lockedSource->width, 'height' => $lockedSource->height],
                        'candidate_dimensions' => ['width' => $width, 'height' => $height],
                        'settings' => $normalized,
                    ],
                    'operations_applied' => array_keys($operations),
                    'review_state' => 'pending',
                ]);

                if ($lockedCurrent instanceof RestorationCandidate) {
                    $lockedCurrent->forceFill([
                        'review_state' => 'rejected',
                        'reviewed_by' => $actor->id,
                        'review_note' => 'Superseded by a non-destructive manual adjustment.',
                        'reviewed_at' => now(),
                    ])->save();
                    $oldJob = $lockedCurrent->job;
                    if ($oldJob instanceof ProcessingJob) {
                        $oldJob->forceFill(['state' => 'rejected'])->save();
                    }
                }

                DB::table('cloud_import_items')->where('id', $itemId)->update([
                    'restoration_candidate_id' => $candidate->id,
                    'attention_code' => null,
                    'prepared_at' => now(),
                    'updated_at' => now(),
                ]);
                ProcessingJobEvent::query()->create([
                    'processing_job_id' => $job->id,
                    'actor_id' => $actor->id,
                    'event' => 'manual_candidate_ready',
                    'safe_context' => [
                        'candidate_id' => $candidateId,
                        'operation_count' => count($operations),
                        'original_retained' => true,
                    ],
                    'occurred_at' => now(),
                ]);

                $this->readAndVerifySource($lockedSource);

                return $candidate;
            }, 5);

            return $candidate;
        } catch (Throwable $exception) {
            $this->writer->removeCreated($written);

            throw $exception;
        }
    }

    private function originalSourceForItem(object $item): ?MediaFileVersion
    {
        $upload = IncomingUpload::query()->find((int) data_get($item, 'incoming_upload_id'));
        if (! $upload instanceof IncomingUpload) {
            return null;
        }

        if ($upload->media_item_id !== null) {
            return $this->preferredOriginalForMediaItem($upload->media_item_id);
        }

        $duplicate = DuplicateCandidate::query()
            ->with(['matchedMediaFileVersion', 'matchedIncomingUpload'])
            ->where('incoming_upload_id', $upload->id)
            ->where('match_method', DuplicateMatchMethod::ExactSha256)
            ->latest('id')
            ->first();
        if (! $duplicate instanceof DuplicateCandidate
            || ! hash_equals($upload->sha256, (string) $duplicate->matched_sha256)) {
            return null;
        }

        $matchedVersion = $duplicate->matchedMediaFileVersion;
        if ($matchedVersion instanceof MediaFileVersion
            && hash_equals($upload->sha256, $matchedVersion->sha256)) {
            return $matchedVersion;
        }

        $matchedUpload = $duplicate->matchedIncomingUpload;
        if (! $matchedUpload instanceof IncomingUpload
            || $matchedUpload->media_item_id === null
            || ! hash_equals($upload->sha256, $matchedUpload->sha256)) {
            return null;
        }

        return $this->preferredOriginalForMediaItem($matchedUpload->media_item_id);
    }

    private function preferredOriginalForMediaItem(int $mediaItemId): ?MediaFileVersion
    {
        return MediaFileVersion::query()
            ->where('media_item_id', $mediaItemId)
            ->where('version_type', MediaFileVersionType::Original)
            ->where('is_preferred', true)
            ->first();
    }

    /**
     * @param  array<string, bool|float|int>  $settings
     * @return array<string, bool|float|int>
     */
    private function normalize(array $settings): array
    {
        $normalized = [
            'orient' => (bool) ($settings['orient'] ?? true),
            'quarter_turn' => max(-2, min(2, (int) ($settings['quarter_turn'] ?? 0))),
            'straighten' => round(max(-8, min(8, (float) ($settings['straighten'] ?? 0))), 1),
            'crop_left' => round(max(0, min(80, (float) ($settings['crop_left'] ?? 0))), 1),
            'crop_top' => round(max(0, min(80, (float) ($settings['crop_top'] ?? 0))), 1),
            'crop_right' => round(max(0, min(80, (float) ($settings['crop_right'] ?? 0))), 1),
            'crop_bottom' => round(max(0, min(80, (float) ($settings['crop_bottom'] ?? 0))), 1),
            'brightness' => max(-40, min(40, (int) ($settings['brightness'] ?? 0))),
            'contrast' => max(-30, min(30, (int) ($settings['contrast'] ?? 0))),
            'red' => max(-20, min(20, (int) ($settings['red'] ?? 0))),
            'green' => max(-20, min(20, (int) ($settings['green'] ?? 0))),
            'blue' => max(-20, min(20, (int) ($settings['blue'] ?? 0))),
            'denoise' => max(0, min(3, (int) ($settings['denoise'] ?? 0))),
            'sharpen' => max(0, min(2, (int) ($settings['sharpen'] ?? 0))),
            'cleanup' => max(0, min(3, (int) ($settings['cleanup'] ?? 0))),
        ];

        if ($normalized['crop_left'] + $normalized['crop_right'] >= 90) {
            throw ValidationException::withMessages(['crop_left' => 'The horizontal crop must retain at least 10% of the image.']);
        }
        if ($normalized['crop_top'] + $normalized['crop_bottom'] >= 90) {
            throw ValidationException::withMessages(['crop_top' => 'The vertical crop must retain at least 10% of the image.']);
        }

        $changed = $normalized['quarter_turn'] !== 0
            || abs((float) $normalized['straighten']) >= 0.1
            || array_sum(array_map(fn (string $key): float => (float) $normalized[$key], ['crop_left', 'crop_top', 'crop_right', 'crop_bottom'])) > 0
            || array_sum(array_map(fn (string $key): int => abs((int) $normalized[$key]), ['brightness', 'contrast', 'red', 'green', 'blue', 'denoise', 'sharpen', 'cleanup'])) > 0;
        if (! $changed) {
            throw ValidationException::withMessages(['editor' => 'Make at least one visible adjustment before saving your edited version.']);
        }

        return $normalized;
    }

    /**
     * @param  array<string, bool|float|int>  $settings
     * @return array{string, int, int, array<string, mixed>}
     */
    private function renderWithProcessingMemory(string $bytes, string $mime, array $settings): array
    {
        $originalLimit = ini_get('memory_limit');
        $processingLimit = (string) config('archive.restoration.memory_limit', '512M');
        if ($processingLimit !== '' && preg_match('/^(?:-1|\d+[KMG]?)$/i', $processingLimit)) {
            ini_set('memory_limit', $processingLimit);
        }

        try {
            return $this->render($bytes, $mime, $settings);
        } finally {
            if ($originalLimit !== '') {
                ini_set('memory_limit', $originalLimit);
            }
        }
    }

    /**
     * @param  array<string, bool|float|int>  $settings
     * @return array{string, int, int, array<string, mixed>}
     */
    private function render(string $bytes, string $mime, array $settings): array
    {
        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            throw new DerivativeGenerationException('The immutable source could not be decoded.');
        }

        $operations = [];
        try {
            if ($settings['orient']) {
                $orientation = $this->readOrientation($bytes, $mime);
                if ($orientation !== 1) {
                    $image = $this->applyOrientation($image, $orientation);
                    $operations['orient'] = ['mode' => 'exif', 'orientation' => $orientation];
                }
            }

            $quarterDegrees = (int) $settings['quarter_turn'] * -90;
            if ($quarterDegrees !== 0) {
                $image = $this->rotate($image, $quarterDegrees);
                $operations['rotate'] = ['degrees' => $quarterDegrees];
            }
            if (abs((float) $settings['straighten']) >= 0.1) {
                $image = $this->rotate($image, -(float) $settings['straighten']);
                $operations['deskew'] = ['manual_degrees' => (float) $settings['straighten']];
            }

            $left = (float) $settings['crop_left'];
            $top = (float) $settings['crop_top'];
            $right = (float) $settings['crop_right'];
            $bottom = (float) $settings['crop_bottom'];
            if ($left + $top + $right + $bottom > 0) {
                $width = imagesx($image);
                $height = imagesy($image);
                $crop = [
                    'x' => (int) round($width * $left / 100),
                    'y' => (int) round($height * $top / 100),
                    'width' => max(1, (int) round($width * (100 - $left - $right) / 100)),
                    'height' => max(1, (int) round($height * (100 - $top - $bottom) / 100)),
                ];
                $cropped = imagecrop($image, $crop);
                if (! $cropped instanceof GdImage) {
                    throw new DerivativeGenerationException('The manual crop could not be rendered.');
                }
                $image = $cropped;
                $operations['crop'] = compact('left', 'top', 'right', 'bottom');
            }

            if ((int) $settings['brightness'] !== 0 || (int) $settings['contrast'] !== 0) {
                imagefilter($image, IMG_FILTER_BRIGHTNESS, (int) $settings['brightness']);
                imagefilter($image, IMG_FILTER_CONTRAST, -(int) $settings['contrast']);
                $operations['exposure'] = [
                    'brightness' => (int) $settings['brightness'],
                    'contrast' => (int) $settings['contrast'],
                ];
            }
            if ((int) $settings['red'] !== 0 || (int) $settings['green'] !== 0 || (int) $settings['blue'] !== 0) {
                imagefilter($image, IMG_FILTER_COLORIZE, (int) $settings['red'], (int) $settings['green'], (int) $settings['blue']);
                $operations['colour'] = ['red' => (int) $settings['red'], 'green' => (int) $settings['green'], 'blue' => (int) $settings['blue']];
            }
            for ($pass = 0; $pass < (int) $settings['denoise']; $pass++) {
                imagefilter($image, IMG_FILTER_SMOOTH, 1);
            }
            if ((int) $settings['denoise'] > 0) {
                $operations['denoise'] = ['passes' => (int) $settings['denoise']];
            }
            for ($pass = 0; $pass < (int) $settings['sharpen']; $pass++) {
                imageconvolution($image, [[-1, -1, -1], [-1, 16, -1], [-1, -1, -1]], 8, 0);
            }
            if ((int) $settings['sharpen'] > 0) {
                $operations['sharpen'] = ['passes' => (int) $settings['sharpen']];
            }
            for ($pass = 0; $pass < (int) $settings['cleanup']; $pass++) {
                imagefilter($image, IMG_FILTER_SMOOTH, 1);
            }
            if ((int) $settings['cleanup'] > 0) {
                $operations['surface_cleanup'] = ['passes' => (int) $settings['cleanup']];
            }

            ob_start();
            $encoded = imagewebp($image, null, 92);
            $candidateBytes = ob_get_clean();
            if (! $encoded || $candidateBytes === '') {
                throw new DerivativeGenerationException('The manually adjusted candidate could not be encoded.');
            }

            return [$candidateBytes, imagesx($image), imagesy($image), $operations];
        } finally {
            unset($image);
        }
    }

    private function readOrientation(string $bytes, string $mime): int
    {
        if (! in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            return 1;
        }
        $exif = strpos($bytes, "Exif\0\0");
        if ($exif === false) {
            return 1;
        }
        $tiff = $exif + 6;
        $order = substr($bytes, $tiff, 2);
        $little = $order === 'II';
        if (! $little && $order !== 'MM') {
            return 1;
        }
        $short = static function (int $offset) use ($bytes, $little): ?int {
            $value = strlen($bytes) >= $offset + 2 ? unpack($little ? 'v' : 'n', substr($bytes, $offset, 2)) : false;

            return is_array($value) ? (int) $value[1] : null;
        };
        $long = static function (int $offset) use ($bytes, $little): ?int {
            $value = strlen($bytes) >= $offset + 4 ? unpack($little ? 'V' : 'N', substr($bytes, $offset, 4)) : false;

            return is_array($value) ? (int) $value[1] : null;
        };
        $ifdOffset = $long($tiff + 4);
        if ($short($tiff + 2) !== 42 || $ifdOffset === null) {
            return 1;
        }
        $ifd = $tiff + $ifdOffset;
        $entries = $short($ifd);
        if ($entries === null || $entries > 512) {
            return 1;
        }
        for ($index = 0; $index < $entries; $index++) {
            $entry = $ifd + 2 + ($index * 12);
            if ($short($entry) === 0x0112) {
                $orientation = $short($entry + 8);

                return $orientation !== null && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
            }
        }

        return 1;
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
            throw new DerivativeGenerationException('The manual rotation could not be rendered.');
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
        abort_unless($disk->exists($source->storage_path), 404);
        $bytes = $disk->get($source->storage_path);
        if (strlen($bytes) !== $source->file_size_bytes || ! hash_equals(strtolower($source->sha256), hash('sha256', $bytes))) {
            throw new DerivativeGenerationException('The immutable source failed integrity verification.');
        }

        return $bytes;
    }

    private function readAndVerifyApprovedSource(MediaFileVersion $source): string
    {
        $isImmutableOriginal = $source->version_type === MediaFileVersionType::Original
            && $source->storage_disk === 'archive_originals'
            && $source->parent_version_id === null;
        $isApprovedArchiveEdit = $source->version_type === MediaFileVersionType::EditedFull
            && $source->storage_disk === 'archive_derivatives'
            && $source->parent_version_id !== null
            && $source->mime_type === 'image/webp'
            && $source->extension === 'webp';

        if ((! $isImmutableOriginal && ! $isApprovedArchiveEdit)
            || $source->generation_status !== GenerationStatus::Ready
            || ! $source->is_preferred) {
            throw new DerivativeGenerationException('A ready preferred approved editing source is required.');
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($source->storage_disk);
        abort_unless($disk->exists($source->storage_path), 404);
        $bytes = $disk->get($source->storage_path);
        if (strlen($bytes) !== $source->file_size_bytes || ! hash_equals(strtolower($source->sha256), hash('sha256', $bytes))) {
            throw new DerivativeGenerationException('The approved editing source failed integrity verification.');
        }

        return $bytes;
    }
}
