<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Contracts\NoOverwriteQuarantineWriter;
use App\Domain\Intake\Exceptions\QuarantinePersistenceException;
use App\Domain\Intake\ValueObjects\WrittenQuarantineObject;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class LocalNoOverwriteQuarantineWriter implements NoOverwriteQuarantineWriter
{
    /** @param resource $source */
    public function write(string $relativePath, $source): WrittenQuarantineObject
    {
        if (! is_resource($source)) {
            throw new QuarantinePersistenceException('The validated upload stream is unavailable.');
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('archive_quarantine');
        if ($disk->exists($relativePath)) {
            throw new QuarantinePersistenceException('The planned quarantine destination already exists.');
        }

        $absolute = $disk->path($relativePath);
        $directory = dirname($absolute);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new QuarantinePersistenceException('The private quarantine directory could not be prepared.');
        }

        $destination = @fopen($absolute, 'xb');
        if ($destination === false) {
            throw new QuarantinePersistenceException('The planned quarantine destination already exists or could not be reserved.');
        }

        $hash = hash_init('sha256');
        $written = 0;
        try {
            while (! feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new QuarantinePersistenceException('The validated upload stream could not be read.');
                }
                if ($chunk === '') {
                    continue;
                }
                hash_update($hash, $chunk);
                $offset = 0;
                $length = strlen($chunk);
                while ($offset < $length) {
                    $count = fwrite($destination, substr($chunk, $offset));
                    if ($count === false || $count === 0) {
                        throw new QuarantinePersistenceException('The quarantine write did not complete.');
                    }
                    $offset += $count;
                    $written += $count;
                }
            }
            if (! fflush($destination)) {
                throw new QuarantinePersistenceException('The quarantine write could not be flushed.');
            }
        } catch (Throwable $e) {
            fclose($destination);
            @unlink($absolute);
            throw $e;
        }
        fclose($destination);

        $stored = filesize($absolute);
        if ($stored === false) {
            @unlink($absolute);
            throw new QuarantinePersistenceException('The retained quarantine byte count could not be verified.');
        }

        return new WrittenQuarantineObject($relativePath, $written, (int) $stored, hash_final($hash));
    }

    public function removeCreated(WrittenQuarantineObject $object): void
    {
        $disk = Storage::disk('archive_quarantine');
        if (! $disk->exists($object->relativePath)) {
            return;
        }
        $current = $disk->checksum($object->relativePath, ['checksum_algo' => 'sha256']);
        if (is_string($current) && hash_equals($object->sha256, strtolower($current))) {
            $disk->delete($object->relativePath);
        }
    }
}
