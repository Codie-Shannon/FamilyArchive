<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Contracts\NoOverwriteOriginalWriter;
use App\Domain\Archive\Exceptions\ArchivePromotionException;
use App\Domain\Archive\ValueObjects\WrittenOriginalObject;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LocalNoOverwriteOriginalWriter implements NoOverwriteOriginalWriter
{
    public function copyFromQuarantine(
        string $sourceRelativePath,
        string $targetRelativePath,
        int $expectedBytes,
        string $expectedSha256,
    ): WrittenOriginalObject {
        /** @var FilesystemAdapter $sourceDisk */
        $sourceDisk = Storage::disk('archive_quarantine');
        /** @var FilesystemAdapter $targetDisk */
        $targetDisk = Storage::disk('archive_originals');

        if (! $sourceDisk->exists($sourceRelativePath)) {
            throw new ArchivePromotionException('The retained quarantine source does not exist.');
        }

        if ($targetDisk->exists($targetRelativePath)) {
            throw new ArchivePromotionException('The planned archive original already exists. Originals are never overwritten.');
        }

        $sourceAbsolute = $sourceDisk->path($sourceRelativePath);
        $targetAbsolute = $targetDisk->path($targetRelativePath);
        $targetDirectory = dirname($targetAbsolute);

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0700, true) && ! is_dir($targetDirectory)) {
            throw new ArchivePromotionException('The private archive original directory could not be prepared.');
        }

        $source = @fopen($sourceAbsolute, 'rb');
        if ($source === false) {
            throw new ArchivePromotionException('The retained quarantine source could not be opened.');
        }

        $target = @fopen($targetAbsolute, 'xb');
        if ($target === false) {
            fclose($source);
            throw new ArchivePromotionException('The planned archive original already exists or could not be reserved.');
        }

        $sourceHash = hash_init('sha256');
        $writtenHash = hash_init('sha256');
        $sourceBytes = 0;
        $writtenBytes = 0;

        try {
            while (! feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new ArchivePromotionException('The retained quarantine source could not be read.');
                }

                if ($chunk === '') {
                    continue;
                }

                $length = strlen($chunk);
                $sourceBytes += $length;
                hash_update($sourceHash, $chunk);

                $offset = 0;
                while ($offset < $length) {
                    $count = fwrite($target, substr($chunk, $offset));
                    if ($count === false || $count === 0) {
                        throw new ArchivePromotionException('The archive original write did not complete.');
                    }

                    $writtenChunk = substr($chunk, $offset, $count);
                    hash_update($writtenHash, $writtenChunk);
                    $writtenBytes += $count;
                    $offset += $count;
                }
            }

            if (! fflush($target)) {
                throw new ArchivePromotionException('The archive original write could not be flushed.');
            }
        } catch (Throwable $exception) {
            fclose($source);
            fclose($target);
            @unlink($targetAbsolute);

            throw $exception;
        }

        fclose($source);
        fclose($target);

        $storedBytes = filesize($targetAbsolute);
        $storedSha256 = hash_file('sha256', $targetAbsolute);
        $sourceSha256 = hash_final($sourceHash);
        $writtenSha256 = hash_final($writtenHash);
        $expectedSha256 = strtolower($expectedSha256);

        if (
            $storedBytes === false
            || $storedSha256 === false
            || $sourceBytes !== $expectedBytes
            || $writtenBytes !== $expectedBytes
            || (int) $storedBytes !== $expectedBytes
            || ! hash_equals($expectedSha256, strtolower($sourceSha256))
            || ! hash_equals($expectedSha256, strtolower($writtenSha256))
            || ! hash_equals($expectedSha256, strtolower($storedSha256))
        ) {
            @unlink($targetAbsolute);
            throw new ArchivePromotionException('Archive original byte or SHA-256 verification failed.');
        }

        return new WrittenOriginalObject(
            $targetRelativePath,
            $sourceBytes,
            $writtenBytes,
            (int) $storedBytes,
            strtolower($sourceSha256),
            strtolower($writtenSha256),
            strtolower($storedSha256),
        );
    }

    public function removeCreated(WrittenOriginalObject $object): void
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_originals');

        if (! $disk->exists($object->relativePath)) {
            return;
        }

        $currentHash = $disk->checksum($object->relativePath, ['checksum_algo' => 'sha256']);
        if (is_string($currentHash) && hash_equals($object->storedSha256, strtolower($currentHash))) {
            $disk->delete($object->relativePath);
        }
    }
}
