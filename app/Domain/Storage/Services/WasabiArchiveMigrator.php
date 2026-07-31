<?php

namespace App\Domain\Storage\Services;

use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Exceptions\WasabiProviderException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

final class WasabiArchiveMigrator
{
    public function __construct(
        private readonly ArchiveProviderReadiness $readiness,
        private readonly WasabiGateway $gateway,
        private readonly WasabiVerifiedObjectWriter $writer,
    ) {}

    /** @return array{planned: int, copied: int, verified_existing: int, bytes: int} */
    public function migrate(bool $execute = false, int $limit = 0): array
    {
        if ($limit < 0) {
            throw new WasabiProviderException('The migration limit cannot be negative.');
        }

        if ($execute) {
            $this->readiness->assertWasabiReady();
        }

        $summary = [
            'planned' => 0,
            'copied' => 0,
            'verified_existing' => 0,
            'bytes' => 0,
        ];

        foreach ($this->boundaries() as $boundary) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($boundary['disk']);
            foreach ($disk->allFiles() as $relativePath) {
                if ($limit > 0 && $summary['planned'] >= $limit) {
                    return $summary;
                }

                $bytes = $disk->size($relativePath);
                $summary['planned']++;
                $summary['bytes'] += $bytes;

                if (! $execute) {
                    continue;
                }

                $source = $disk->readStream($relativePath);
                if (! is_resource($source)) {
                    throw new WasabiProviderException('A private local archive object could not be opened.');
                }

                try {
                    if ($this->gateway->objectExists($boundary['prefix'], $relativePath)) {
                        $localFacts = $this->writer->facts($source);
                        rewind($source);
                        $remote = $this->gateway->readStream($boundary['prefix'], $relativePath);
                        $remoteFacts = $this->writer->facts($remote);
                        fclose($remote);

                        if ($localFacts !== $remoteFacts) {
                            throw new WasabiProviderException('A remote path collision did not match the local integrity facts.');
                        }

                        $summary['verified_existing']++;

                        continue;
                    }

                    $this->writer->write(
                        $boundary['prefix'],
                        $relativePath,
                        $source,
                        $bytes,
                        cleanupOnVerificationFailure: $boundary['cleanup_on_failure'],
                    );
                    $summary['copied']++;
                } finally {
                    fclose($source);
                }
            }
        }

        return $summary;
    }

    /** @return list<array{disk: string, prefix: string, cleanup_on_failure: bool}> */
    private function boundaries(): array
    {
        $prefixes = (array) config('archive_providers.providers.wasabi.prefixes');

        return [
            ['disk' => 'archive_local_originals', 'prefix' => (string) $prefixes['archive_originals'], 'cleanup_on_failure' => false],
            ['disk' => 'archive_local_derivatives', 'prefix' => (string) $prefixes['archive_derivatives'], 'cleanup_on_failure' => true],
            ['disk' => 'archive_local_quarantine', 'prefix' => (string) $prefixes['archive_quarantine'], 'cleanup_on_failure' => true],
            ['disk' => 'archive_local_manifests', 'prefix' => (string) $prefixes['archive_manifests'], 'cleanup_on_failure' => false],
        ];
    }
}
