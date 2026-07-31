<?php

namespace App\Domain\Storage\Services;

use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Exceptions\WasabiObjectExistsException;
use App\Domain\Storage\Exceptions\WasabiProviderException;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;

final class AwsWasabiGateway implements WasabiGateway
{
    private ?S3ClientInterface $client = null;

    public function __construct(
        private readonly ArchiveProviderReadiness $readiness,
    ) {}

    public function putStreamIfAbsent(
        string $prefix,
        string $relativePath,
        $stream,
        int $contentLength,
        string $contentMd5,
        string $contentType = 'application/octet-stream',
    ): string {
        if (! is_resource($stream)) {
            throw new WasabiProviderException('The Wasabi upload stream is unavailable.');
        }

        rewind($stream);

        try {
            $result = $this->client()->putObject([
                'Bucket' => $this->bucket(),
                'Key' => $this->key($prefix, $relativePath),
                'Body' => $stream,
                'ContentLength' => $contentLength,
                'ContentMD5' => $contentMd5,
                'ContentType' => $contentType,
                'IfNoneMatch' => '*',
            ]);
        } catch (AwsException $exception) {
            if (
                in_array($exception->getAwsErrorCode(), ['PreconditionFailed', 'ConditionalRequestConflict'], true)
                || in_array($exception->getStatusCode(), [409, 412], true)
            ) {
                throw new WasabiObjectExistsException('The remote object already exists; no-overwrite storage refused.', previous: $exception);
            }

            throw $this->providerFailure($exception);
        } catch (Throwable $exception) {
            throw $this->providerFailure($exception);
        }

        $versionId = $result->get('VersionId');
        if (! is_string($versionId) || $versionId === '') {
            throw new WasabiProviderException('Wasabi did not return a version identity; the write failed closed.');
        }

        return $versionId;
    }

    public function readStream(string $prefix, string $relativePath, ?string $versionId = null)
    {
        $request = [
            'Bucket' => $this->bucket(),
            'Key' => $this->key($prefix, $relativePath),
        ];

        if (is_string($versionId) && $versionId !== '') {
            $request['VersionId'] = $versionId;
        }

        try {
            $body = $this->client()->getObject($request)->get('Body');
        } catch (Throwable $exception) {
            throw $this->providerFailure($exception);
        }

        if ($body instanceof StreamInterface) {
            $resource = $body->detach();
            if (is_resource($resource)) {
                return $resource;
            }

            $resource = fopen('php://temp', 'w+b');
            if ($resource !== false) {
                fwrite($resource, (string) $body);
                rewind($resource);

                return $resource;
            }
        }

        throw new WasabiProviderException('Wasabi returned an unreadable object stream.');
    }

    public function objectExists(string $prefix, string $relativePath): bool
    {
        try {
            $this->client()->headObject([
                'Bucket' => $this->bucket(),
                'Key' => $this->key($prefix, $relativePath),
            ]);

            return true;
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 404 || in_array($exception->getAwsErrorCode(), ['NoSuchKey', 'NotFound'], true)) {
                return false;
            }

            throw $this->providerFailure($exception);
        } catch (Throwable $exception) {
            throw $this->providerFailure($exception);
        }
    }

    public function deleteVersion(string $prefix, string $relativePath, string $versionId): void
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket(),
                'Key' => $this->key($prefix, $relativePath),
                'VersionId' => $versionId,
            ]);
        } catch (Throwable $exception) {
            throw $this->providerFailure($exception);
        }
    }

    public function bucketProtection(): array
    {
        try {
            $versioning = $this->client()->getBucketVersioning(['Bucket' => $this->bucket()]);
            $objectLock = $this->client()->getObjectLockConfiguration(['Bucket' => $this->bucket()]);
        } catch (Throwable $exception) {
            throw $this->providerFailure($exception);
        }

        return [
            'versioning_enabled' => $versioning->get('Status') === 'Enabled',
            'object_lock_enabled' => data_get($objectLock->toArray(), 'ObjectLockConfiguration.ObjectLockEnabled') === 'Enabled',
        ];
    }

    private function client(): S3ClientInterface
    {
        $this->readiness->assertWasabiReady();

        if ($this->client instanceof S3ClientInterface) {
            return $this->client;
        }

        $wasabi = config('archive_providers.providers.wasabi');
        if (! is_array($wasabi)) {
            throw new WasabiProviderException('Wasabi configuration is unavailable.');
        }

        return $this->client = new S3Client([
            'version' => 'latest',
            'region' => (string) $wasabi['region'],
            'endpoint' => (string) $wasabi['endpoint'],
            'use_path_style_endpoint' => (bool) ($wasabi['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => (string) $wasabi['key'],
                'secret' => (string) $wasabi['secret'],
            ],
            'http' => [
                'connect_timeout' => 5,
                'timeout' => 30,
            ],
        ]);
    }

    private function bucket(): string
    {
        $bucket = config('archive_providers.providers.wasabi.bucket');
        if (! is_string($bucket) || $bucket === '') {
            throw new WasabiProviderException('Wasabi bucket configuration is unavailable.');
        }

        return $bucket;
    }

    private function key(string $prefix, string $relativePath): string
    {
        $prefix = trim($prefix, '/');
        $relativePath = ltrim($relativePath, '/');

        if (
            $prefix === ''
            || $relativePath === ''
            || str_contains($relativePath, '..')
            || str_contains($relativePath, '\\')
            || str_contains($relativePath, "\0")
        ) {
            throw new WasabiProviderException('The Wasabi object key failed the relative-path boundary.');
        }

        return "{$prefix}/{$relativePath}";
    }

    private function providerFailure(Throwable $exception): WasabiProviderException
    {
        return new WasabiProviderException(
            'The Wasabi provider request failed; storage remained fail-closed.',
            previous: $exception,
        );
    }
}
