<?php

namespace App\Domain\Derivatives\Services;

use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Derivatives\ValueObjects\WrittenDerivativeObject;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LocalNoOverwriteDerivativeWriter implements NoOverwriteDerivativeWriter
{
    public function write(string $relativePath, string $bytes): WrittenDerivativeObject
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_derivatives');

        if ($disk->exists($relativePath)) {
            throw new DerivativeGenerationException('The planned derivative already exists. Derivatives are never overwritten.');
        }

        $absolute = $disk->path($relativePath);
        $directory = dirname($absolute);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new DerivativeGenerationException('The private derivative directory could not be prepared.');
        }

        $target = @fopen($absolute, 'xb');
        if ($target === false) {
            throw new DerivativeGenerationException('The planned derivative already exists or could not be reserved.');
        }

        $expectedBytes = strlen($bytes);
        $expectedSha256 = hash('sha256', $bytes);
        $offset = 0;

        try {
            while ($offset < $expectedBytes) {
                $written = fwrite($target, substr($bytes, $offset));
                if ($written === false || $written === 0) {
                    throw new DerivativeGenerationException('The derivative write did not complete.');
                }
                $offset += $written;
            }

            if (! fflush($target)) {
                throw new DerivativeGenerationException('The derivative write could not be flushed.');
            }
        } catch (Throwable $exception) {
            fclose($target);
            @unlink($absolute);
            throw $exception;
        }

        fclose($target);

        $storedBytes = filesize($absolute);
        $storedSha256 = hash_file('sha256', $absolute);
        if (
            $storedBytes === false
            || $storedSha256 === false
            || (int) $storedBytes !== $expectedBytes
            || ! hash_equals($expectedSha256, strtolower($storedSha256))
        ) {
            @unlink($absolute);
            throw new DerivativeGenerationException('The stored derivative failed byte or SHA-256 verification.');
        }

        return new WrittenDerivativeObject($relativePath, $expectedBytes, $expectedSha256);
    }

    public function removeCreated(WrittenDerivativeObject $object): void
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_derivatives');

        if (! $disk->exists($object->relativePath)) {
            return;
        }

        $current = $disk->checksum($object->relativePath, ['checksum_algo' => 'sha256']);
        if (is_string($current) && hash_equals($object->sha256, strtolower($current))) {
            $disk->delete($object->relativePath);
        }
    }
}
