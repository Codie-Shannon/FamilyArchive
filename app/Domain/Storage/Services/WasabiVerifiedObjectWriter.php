<?php

namespace App\Domain\Storage\Services;

use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Exceptions\WasabiProviderException;
use App\Domain\Storage\ValueObjects\VerifiedWasabiObject;
use Throwable;

final class WasabiVerifiedObjectWriter
{
    public function __construct(
        private readonly WasabiGateway $gateway,
    ) {}

    /**
     * @param  resource  $source
     */
    public function write(
        string $prefix,
        string $relativePath,
        $source,
        ?int $expectedBytes = null,
        ?string $expectedSha256 = null,
        string $contentType = 'application/octet-stream',
        bool $cleanupOnVerificationFailure = true,
    ): VerifiedWasabiObject {
        if (! is_resource($source)) {
            throw new WasabiProviderException('The source stream is unavailable.');
        }

        $spool = fopen('php://temp/maxmemory:5242880', 'w+b');
        if ($spool === false) {
            throw new WasabiProviderException('The verified upload buffer could not be prepared.');
        }

        $sha256 = hash_init('sha256');
        $md5 = hash_init('md5');
        $bytes = 0;

        try {
            rewind($source);
            while (! feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new WasabiProviderException('The source stream could not be read.');
                }
                if ($chunk === '') {
                    continue;
                }

                $length = strlen($chunk);
                $offset = 0;
                while ($offset < $length) {
                    $written = fwrite($spool, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        throw new WasabiProviderException('The verified upload buffer could not be written.');
                    }
                    $offset += $written;
                }

                hash_update($sha256, $chunk);
                hash_update($md5, $chunk);
                $bytes += $length;
            }

            $sourceSha256 = strtolower(hash_final($sha256));
            $contentMd5 = base64_encode(hash_final($md5, true));
            if (
                ($expectedBytes !== null && $bytes !== $expectedBytes)
                || ($expectedSha256 !== null && ! hash_equals(strtolower($expectedSha256), $sourceSha256))
            ) {
                throw new WasabiProviderException('The source facts changed before the remote write.');
            }

            rewind($spool);
            $versionId = $this->gateway->putStreamIfAbsent(
                $prefix,
                $relativePath,
                $spool,
                $bytes,
                $contentMd5,
                $contentType,
            );

            try {
                $stored = $this->gateway->readStream($prefix, $relativePath, $versionId);
                [$storedBytes, $storedSha256] = $this->facts($stored);
                fclose($stored);

                if ($storedBytes !== $bytes || ! hash_equals($sourceSha256, $storedSha256)) {
                    throw new WasabiProviderException('The remote object failed byte or SHA-256 verification.');
                }
            } catch (Throwable $exception) {
                if ($cleanupOnVerificationFailure) {
                    $this->gateway->deleteVersion($prefix, $relativePath, $versionId);
                }
                throw $exception;
            }

            return new VerifiedWasabiObject($relativePath, $bytes, $sourceSha256, $versionId);
        } finally {
            fclose($spool);
        }
    }

    /**
     * @param  resource  $stream
     * @return array{int, string}
     */
    public function facts($stream): array
    {
        if (! is_resource($stream)) {
            throw new WasabiProviderException('The verification stream is unavailable.');
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                throw new WasabiProviderException('The verification stream could not be read.');
            }
            if ($chunk === '') {
                continue;
            }
            $bytes += strlen($chunk);
            hash_update($hash, $chunk);
        }

        return [$bytes, strtolower(hash_final($hash))];
    }
}
