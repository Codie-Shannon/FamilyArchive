<?php

namespace App\Domain\Storage\Contracts;

interface WasabiGateway
{
    /**
     * @param  resource  $stream
     */
    public function putStreamIfAbsent(
        string $prefix,
        string $relativePath,
        $stream,
        int $contentLength,
        string $contentMd5,
        string $contentType = 'application/octet-stream',
    ): string;

    /** @return resource */
    public function readStream(string $prefix, string $relativePath, ?string $versionId = null);

    public function objectExists(string $prefix, string $relativePath): bool;

    public function deleteVersion(string $prefix, string $relativePath, string $versionId): void;

    /** @return array{versioning_enabled: bool, object_lock_enabled: bool} */
    public function bucketProtection(): array;
}
