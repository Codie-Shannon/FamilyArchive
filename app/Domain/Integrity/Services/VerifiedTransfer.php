<?php

namespace App\Domain\Integrity\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;

final class VerifiedTransfer
{
    /**
     * @return array{bytes: int, sha256: string}
     */
    public function copy(
        Filesystem $source,
        Filesystem $target,
        string $sourcePath,
        string $targetPath,
        string $expectedHash,
    ): array {
        if ($target->exists($targetPath)) {
            throw ValidationException::withMessages([
                'target' => 'The target already exists; no-overwrite transfer refused.',
            ]);
        }

        $bytes = $source->get($sourcePath);
        $hash = hash('sha256', $bytes);

        if (! hash_equals(strtolower($expectedHash), $hash)) {
            throw ValidationException::withMessages([
                'source' => 'Source hash does not match the expected archive identity.',
            ]);
        }

        if (! $target->put($targetPath, $bytes)) {
            throw ValidationException::withMessages([
                'target' => 'The destination write failed; cutover refused.',
            ]);
        }

        try {
            $written = $target->get($targetPath);
        } catch (\Throwable) {
            $target->delete($targetPath);

            throw ValidationException::withMessages([
                'target' => 'The destination could not be read back; cutover refused.',
            ]);
        }

        if (strlen($written) !== strlen($bytes) || ! hash_equals($hash, hash('sha256', $written))) {
            $target->delete($targetPath);

            throw ValidationException::withMessages([
                'target' => 'Destination verification failed; cutover refused.',
            ]);
        }

        return [
            'bytes' => strlen($written),
            'sha256' => $hash,
        ];
    }
}
