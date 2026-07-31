<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Contracts\NoOverwriteOriginalWriter;
use App\Domain\Archive\Exceptions\ArchivePromotionException;
use App\Domain\Archive\ValueObjects\WrittenOriginalObject;
use App\Domain\Storage\Services\WasabiVerifiedObjectWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class WasabiNoOverwriteOriginalWriter implements NoOverwriteOriginalWriter
{
    public function __construct(
        private readonly WasabiVerifiedObjectWriter $writer,
    ) {}

    public function copyFromQuarantine(
        string $sourceRelativePath,
        string $targetRelativePath,
        int $expectedBytes,
        string $expectedSha256,
    ): WrittenOriginalObject {
        $source = Storage::disk('archive_quarantine')->readStream($sourceRelativePath);
        if (! is_resource($source)) {
            throw new ArchivePromotionException('The retained quarantine source could not be opened.');
        }

        try {
            $written = $this->writer->write(
                $this->prefix(),
                $targetRelativePath,
                $source,
                $expectedBytes,
                $expectedSha256,
                cleanupOnVerificationFailure: false,
            );
        } catch (Throwable $exception) {
            throw new ArchivePromotionException(
                'The immutable Wasabi original could not be stored and verified.',
                previous: $exception,
            );
        } finally {
            fclose($source);
        }

        return new WrittenOriginalObject(
            $targetRelativePath,
            $written->bytes,
            $written->bytes,
            $written->bytes,
            $written->sha256,
            $written->sha256,
            $written->sha256,
            $written->versionId,
        );
    }

    public function removeCreated(WrittenOriginalObject $object): void
    {
        Log::warning('An immutable Wasabi original was retained after a database rollback.', [
            'relative_path_hash' => hash('sha256', $object->relativePath),
            'provider_version_recorded' => $object->providerVersionId !== null,
        ]);
    }

    private function prefix(): string
    {
        return (string) config('archive_providers.providers.wasabi.prefixes.archive_originals');
    }
}
