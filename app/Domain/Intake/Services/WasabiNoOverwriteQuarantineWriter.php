<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Contracts\NoOverwriteQuarantineWriter;
use App\Domain\Intake\Exceptions\QuarantinePersistenceException;
use App\Domain\Intake\ValueObjects\WrittenQuarantineObject;
use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Services\WasabiVerifiedObjectWriter;
use Throwable;

final class WasabiNoOverwriteQuarantineWriter implements NoOverwriteQuarantineWriter
{
    public function __construct(
        private readonly WasabiVerifiedObjectWriter $writer,
        private readonly WasabiGateway $gateway,
    ) {}

    public function write(string $relativePath, $source): WrittenQuarantineObject
    {
        try {
            $written = $this->writer->write($this->prefix(), $relativePath, $source);
        } catch (Throwable $exception) {
            throw new QuarantinePersistenceException(
                'The Wasabi quarantine object could not be stored and verified.',
                previous: $exception,
            );
        }

        return new WrittenQuarantineObject(
            $relativePath,
            $written->bytes,
            $written->bytes,
            $written->sha256,
            $written->versionId,
        );
    }

    public function removeCreated(WrittenQuarantineObject $object): void
    {
        if ($object->providerVersionId === null) {
            return;
        }

        $this->gateway->deleteVersion($this->prefix(), $object->relativePath, $object->providerVersionId);
    }

    private function prefix(): string
    {
        return (string) config('archive_providers.providers.wasabi.prefixes.archive_quarantine');
    }
}
