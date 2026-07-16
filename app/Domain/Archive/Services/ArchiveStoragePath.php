<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Enums\ArchiveStorageDisk;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaType;
use InvalidArgumentException;

class ArchiveStoragePath
{
    public function __construct(private readonly StoragePathValidator $validator) {}

    /** @return array{disk: ArchiveStorageDisk, path: string} */
    public function original(MediaType $mediaType, string $archiveId, string $extension): array
    {
        return [
            'disk' => ArchiveStorageDisk::Originals,
            'path' => $this->validator->validateRelativePath(
                $this->basePath($mediaType, $archiveId).'.'.$this->validator->normalizeExtension($extension),
            ),
        ];
    }

    /** @return array{disk: ArchiveStorageDisk, path: string} */
    public function derivative(MediaFileVersionType $versionType, MediaType $mediaType, string $archiveId, string $extension): array
    {
        if ($versionType === MediaFileVersionType::Original) {
            throw new InvalidArgumentException('Original paths must use the original path contract.');
        }

        $segment = config("archive.version_segments.{$versionType->value}");
        if (! is_string($segment) || $segment === '') {
            throw new InvalidArgumentException("No derivative path segment is configured for {$versionType->value}.");
        }

        return [
            'disk' => ArchiveStorageDisk::Derivatives,
            'path' => $this->validator->validateRelativePath(
                $segment.'/'.$this->basePath($mediaType, $archiveId).'.'.$this->validator->normalizeExtension($extension),
            ),
        ];
    }

    /** @return array{disk: ArchiveStorageDisk, path: string} */
    public function quarantine(string $area, string $uploadId, string $filename): array
    {
        if (! in_array($area, ['incoming', 'duplicates', 'failed'], true)) {
            throw new InvalidArgumentException('Unknown quarantine area.');
        }

        if (! preg_match('/^[A-Z0-9_-]+$/', $uploadId)) {
            throw new InvalidArgumentException('Upload IDs must contain uppercase letters, numbers, underscores or hyphens only.');
        }

        return [
            'disk' => ArchiveStorageDisk::Quarantine,
            'path' => $this->validator->validateRelativePath(
                $area.'/'.$uploadId.'/'.$this->validator->sanitizeFilename($filename),
            ),
        ];
    }

    /** @return array{disk: ArchiveStorageDisk, path: string} */
    public function manifest(MediaType $mediaType, string $archiveId): array
    {
        return [
            'disk' => ArchiveStorageDisk::Manifests,
            'path' => $this->validator->validateRelativePath(
                'media/'.$this->basePath($mediaType, $archiveId).'.json',
            ),
        ];
    }

    public function bucketForArchiveId(string $archiveId): string
    {
        if (! preg_match('/^[A-Z]{2}_(\d{6,})$/', $archiveId, $matches)) {
            throw new InvalidArgumentException('Archive IDs must contain a two-letter prefix and at least six digits.');
        }

        $bucketSize = (int) config('archive.bucket_size', 1000);
        if ($bucketSize < 1) {
            throw new InvalidArgumentException('Archive bucket size must be positive.');
        }

        return sprintf('%03d', intdiv((int) $matches[1], $bucketSize));
    }

    private function basePath(MediaType $mediaType, string $archiveId): string
    {
        $expectedPrefix = config("archive.prefixes.{$mediaType->value}");
        if (! is_string($expectedPrefix) || ! str_starts_with($archiveId, $expectedPrefix.'_')) {
            throw new InvalidArgumentException('Archive ID prefix does not match the media type.');
        }

        $segment = config("archive.media_segments.{$mediaType->value}");
        if (! is_string($segment) || $segment === '') {
            throw new InvalidArgumentException("No media path segment is configured for {$mediaType->value}.");
        }

        return $segment.'/'.$this->bucketForArchiveId($archiveId).'/'.$archiveId;
    }
}
