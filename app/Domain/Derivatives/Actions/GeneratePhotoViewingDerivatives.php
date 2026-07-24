<?php

namespace App\Domain\Derivatives\Actions;

use App\Domain\Archive\Enums\ArchiveStorageDisk;
use App\Domain\Archive\Services\ArchiveStoragePath;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Derivatives\Services\GdPhotoDerivativeEncoder;
use App\Domain\Derivatives\Services\PhotoDerivativeRecipe;
use App\Domain\Derivatives\ValueObjects\EncodedDerivative;
use App\Domain\Derivatives\ValueObjects\PhotoDerivativeGenerationResult;
use App\Domain\Derivatives\ValueObjects\WrittenDerivativeObject;
use App\Domain\Media\Enums\GenerationStatus;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GeneratePhotoViewingDerivatives
{
    public function __construct(
        private ArchiveStoragePath $paths,
        private PhotoDerivativeRecipe $recipe,
        private GdPhotoDerivativeEncoder $encoder,
        private NoOverwriteDerivativeWriter $writer,
    ) {}

    public function handle(MediaItem $mediaItem, User $actor): PhotoDerivativeGenerationResult
    {
        if ($actor->role !== 'owner' || $actor->email_verified_at === null) {
            throw new DerivativeGenerationException('Only a verified Owner may generate viewing derivatives.');
        }

        $this->encoder->assertSupported();

        [$lockedItem, $original] = DB::transaction(function () use ($mediaItem): array {
            $item = MediaItem::query()->lockForUpdate()->findOrFail($mediaItem->id);
            $this->assertEligibleItem($item);

            $original = MediaFileVersion::query()
                ->where('media_item_id', $item->id)
                ->where('version_type', MediaFileVersionType::Original)
                ->where('generation_status', GenerationStatus::Ready)
                ->where('is_preferred', true)
                ->lockForUpdate()
                ->first();

            if (! $original instanceof MediaFileVersion) {
                throw new DerivativeGenerationException('A ready preferred original is required.');
            }

            $this->assertEligibleOriginal($original);

            return [$item, $original];
        });

        $sourceBytes = $this->readAndVerifyOriginal($original);
        $existing = $this->matchingExisting($lockedItem, $original);

        $web = $existing[MediaFileVersionType::WebDisplay->value] ?? null;
        $thumbnail = $existing[MediaFileVersionType::Thumbnail->value] ?? null;

        if ($web instanceof MediaFileVersion && $thumbnail instanceof MediaFileVersion) {
            return new PhotoDerivativeGenerationResult($original, $web, $thumbnail, false, false);
        }

        /** @var array<string, EncodedDerivative> $encoded */
        $encoded = [];
        /** @var array<string, array{disk: ArchiveStorageDisk, path: string}> $targets */
        $targets = [];

        foreach ($this->recipe->types() as $type) {
            if (($existing[$type->value] ?? null) instanceof MediaFileVersion) {
                continue;
            }

            $targetRecipe = $this->recipe->target($type);
            $encoded[$type->value] = $this->encoder->encode(
                $sourceBytes,
                $original->mime_type,
                $targetRecipe['max_long_side'],
                $targetRecipe['quality'],
            );
            $targets[$type->value] = $this->paths->derivative(
                $type,
                MediaType::Photo,
                $lockedItem->archive_id,
                'webp',
            );
        }

        $this->readAndVerifyOriginal($original);

        /** @var array<string, WrittenDerivativeObject> $written */
        $written = [];
        $committed = false;

        try {
            foreach ($encoded as $typeValue => $output) {
                $target = $targets[$typeValue];
                if ($target['disk']->value !== 'archive_derivatives') {
                    throw new DerivativeGenerationException('Derivative output was planned outside archive_derivatives.');
                }

                $written[$typeValue] = $this->writer->write($target['path'], $output->bytes);
            }

            $result = DB::transaction(function () use (
                $lockedItem,
                $original,
                $existing,
                $encoded,
                $targets,
                $written,
            ): PhotoDerivativeGenerationResult {
                $item = MediaItem::query()->lockForUpdate()->findOrFail($lockedItem->id);
                $source = MediaFileVersion::query()->lockForUpdate()->findOrFail($original->id);
                $this->assertEligibleItem($item);
                $this->assertEligibleOriginal($source);
                $this->readAndVerifyOriginal($source);

                $versions = $existing;
                $created = [];

                foreach ($encoded as $typeValue => $output) {
                    $type = MediaFileVersionType::from($typeValue);
                    $object = $written[$typeValue];
                    $target = $targets[$typeValue];

                    $concurrent = $this->findMatchingVersion($item, $source, $type);
                    if ($concurrent instanceof MediaFileVersion) {
                        throw new DerivativeGenerationException('A matching derivative was generated concurrently.');
                    }

                    $versions[$typeValue] = MediaFileVersion::query()->create([
                        'media_item_id' => $item->id,
                        'parent_version_id' => $source->id,
                        'version_type' => $type,
                        'storage_disk' => $target['disk']->value,
                        'storage_path' => $target['path'],
                        'mime_type' => 'image/webp',
                        'extension' => 'webp',
                        'file_size_bytes' => $object->bytes,
                        'width' => $output->width,
                        'height' => $output->height,
                        'duration_ms' => null,
                        'sha256' => $object->sha256,
                        'perceptual_hash' => null,
                        'generation_status' => GenerationStatus::Ready,
                        'generation_recipe' => $this->recipe->metadata(
                            $type,
                            $source->sha256,
                            $output->quality,
                            $output->maxLongSide,
                            $output->encoder,
                            $output->sourceOrientation,
                            $output->orientationApplied,
                        ),
                        'is_preferred' => true,
                    ]);
                    $created[$typeValue] = true;
                }

                $web = $versions[MediaFileVersionType::WebDisplay->value] ?? null;
                $thumbnail = $versions[MediaFileVersionType::Thumbnail->value] ?? null;
                if (! $web instanceof MediaFileVersion || ! $thumbnail instanceof MediaFileVersion) {
                    throw new DerivativeGenerationException('Both verified viewing derivative records are required.');
                }

                return new PhotoDerivativeGenerationResult(
                    $source,
                    $web,
                    $thumbnail,
                    isset($created[MediaFileVersionType::WebDisplay->value]),
                    isset($created[MediaFileVersionType::Thumbnail->value]),
                );
            }, 5);

            $committed = true;
            $this->readAndVerifyOriginal($original);

            return $result;
        } catch (Throwable $exception) {
            if (! $committed) {
                foreach ($written as $object) {
                    $this->writer->removeCreated($object);
                }
            }

            throw $exception;
        }
    }

    public function isEligible(MediaItem $mediaItem): bool
    {
        try {
            $this->assertEligibleItem($mediaItem);
            $original = $mediaItem->fileVersions
                ->first(fn (MediaFileVersion $version): bool => $version->version_type === MediaFileVersionType::Original
                    && $version->generation_status === GenerationStatus::Ready
                    && $version->is_preferred
                );

            if (! $original instanceof MediaFileVersion) {
                return false;
            }

            $this->assertEligibleOriginal($original);
            $this->readAndVerifyOriginal($original);

            return true;
        } catch (DerivativeGenerationException) {
            return false;
        }
    }

    /** @return array<string, MediaFileVersion> */
    public function matchingExisting(MediaItem $item, MediaFileVersion $original): array
    {
        $matches = [];
        foreach ($this->recipe->types() as $type) {
            $version = $this->findMatchingVersion($item, $original, $type);
            if ($version instanceof MediaFileVersion) {
                $this->verifyDerivativeObject($version);
                $matches[$type->value] = $version;
            }
        }

        return $matches;
    }

    private function assertEligibleItem(MediaItem $item): void
    {
        if ($item->media_type !== MediaType::Photo || $item->review_status !== MediaReviewStatus::Approved) {
            throw new DerivativeGenerationException('Only approved photo MediaItems may generate viewing derivatives.');
        }
    }

    private function assertEligibleOriginal(MediaFileVersion $original): void
    {
        if (
            $original->version_type !== MediaFileVersionType::Original
            || $original->storage_disk !== 'archive_originals'
            || $original->generation_status !== GenerationStatus::Ready
            || ! $original->is_preferred
            || $original->parent_version_id !== null
            || $original->file_size_bytes < 1
            || ! preg_match('/^[a-f0-9]{64}$/', strtolower($original->sha256))
        ) {
            throw new DerivativeGenerationException('The preferred original record is not eligible for derivative generation.');
        }
    }

    private function readAndVerifyOriginal(MediaFileVersion $original): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($original->storage_disk);
        if (! $disk->exists($original->storage_path)) {
            throw new DerivativeGenerationException('The approved original object does not exist.');
        }

        $bytes = $disk->get($original->storage_path);
        if (
            strlen($bytes) !== $original->file_size_bytes
            || ! hash_equals(strtolower($original->sha256), hash('sha256', $bytes))
        ) {
            throw new DerivativeGenerationException('The approved original no longer matches its database integrity facts.');
        }

        return $bytes;
    }

    private function findMatchingVersion(
        MediaItem $item,
        MediaFileVersion $original,
        MediaFileVersionType $type,
    ): ?MediaFileVersion {
        $versions = MediaFileVersion::query()
            ->where('media_item_id', $item->id)
            ->where('parent_version_id', $original->id)
            ->where('version_type', $type)
            ->get();

        foreach ($versions as $version) {
            $recipe = $version->generation_recipe;
            if (
                $version->generation_status === GenerationStatus::Ready
                && $version->is_preferred
                && is_array($recipe)
                && ($recipe['recipe_version'] ?? null) === PhotoDerivativeRecipe::VERSION
                && ($recipe['source_sha256'] ?? null) === strtolower($original->sha256)
            ) {
                return $version;
            }
        }

        if ($versions->isNotEmpty()) {
            throw new DerivativeGenerationException("A non-matching {$type->value} record already exists. Group 09 does not replace derivatives.");
        }

        return null;
    }

    private function verifyDerivativeObject(MediaFileVersion $version): void
    {
        if (
            ! in_array($version->version_type, [MediaFileVersionType::WebDisplay, MediaFileVersionType::Thumbnail], true)
            || $version->storage_disk !== 'archive_derivatives'
            || $version->mime_type !== 'image/webp'
            || $version->extension !== 'webp'
        ) {
            throw new DerivativeGenerationException('An existing derivative record violates the Group 09 contract.');
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_derivatives');
        if (! $disk->exists($version->storage_path)) {
            throw new DerivativeGenerationException('An existing derivative object is missing.');
        }

        $bytes = $disk->get($version->storage_path);
        $facts = @getimagesizefromstring($bytes);
        if (
            strlen($bytes) !== $version->file_size_bytes
            || ! hash_equals(strtolower($version->sha256), hash('sha256', $bytes))
            || ! is_array($facts)
            || $facts['mime'] !== 'image/webp'
            || (int) $facts[0] !== $version->width
            || (int) $facts[1] !== $version->height
        ) {
            throw new DerivativeGenerationException('An existing derivative failed integrity verification.');
        }
    }
}
