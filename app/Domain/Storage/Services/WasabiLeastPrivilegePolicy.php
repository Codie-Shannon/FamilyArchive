<?php

namespace App\Domain\Storage\Services;

use InvalidArgumentException;

final class WasabiLeastPrivilegePolicy
{
    /** @return array<string, mixed> */
    public function forBucket(string $bucket): array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket)) {
            throw new InvalidArgumentException('The bucket name is invalid.');
        }

        $prefixes = (array) config('archive_providers.providers.wasabi.prefixes', []);
        $required = [
            'archive_originals',
            'archive_derivatives',
            'archive_quarantine',
            'archive_manifests',
            'health',
        ];
        foreach ($required as $name) {
            if (! is_string($prefixes[$name] ?? null) || trim((string) $prefixes[$name], '/') === '') {
                throw new InvalidArgumentException('A required archive prefix is unavailable.');
            }
        }

        $objectResources = array_map(
            fn (string $name): string => 'arn:aws:s3:::'.$bucket.'/'.trim((string) $prefixes[$name], '/').'/*',
            $required,
        );
        $deletableResources = array_map(
            fn (string $name): string => 'arn:aws:s3:::'.$bucket.'/'.trim((string) $prefixes[$name], '/').'/*',
            ['archive_derivatives', 'archive_quarantine', 'health'],
        );

        return [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'VerifyPrivateArchiveBucketProtection',
                    'Effect' => 'Allow',
                    'Action' => [
                        's3:GetBucketLocation',
                        's3:GetBucketVersioning',
                        's3:GetBucketObjectLockConfiguration',
                    ],
                    'Resource' => 'arn:aws:s3:::'.$bucket,
                ],
                [
                    'Sid' => 'ReadAndCreatePrivateArchiveObjects',
                    'Effect' => 'Allow',
                    'Action' => [
                        's3:GetObject',
                        's3:GetObjectVersion',
                        's3:PutObject',
                        's3:AbortMultipartUpload',
                        's3:ListMultipartUploadParts',
                    ],
                    'Resource' => $objectResources,
                ],
                [
                    'Sid' => 'CleanOnlyReplaceableAndHealthVersions',
                    'Effect' => 'Allow',
                    'Action' => 's3:DeleteObjectVersion',
                    'Resource' => $deletableResources,
                ],
            ],
        ];
    }

    public function json(string $bucket): string
    {
        return (string) json_encode(
            $this->forBucket($bucket),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }
}
