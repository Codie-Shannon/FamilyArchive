<?php

namespace App\Domain\Derivatives\Services;

use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Exceptions\DerivativeGenerationException;
use App\Domain\Derivatives\ValueObjects\WrittenDerivativeObject;
use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Services\WasabiVerifiedObjectWriter;
use Throwable;

final class WasabiNoOverwriteDerivativeWriter implements NoOverwriteDerivativeWriter
{
    public function __construct(
        private readonly WasabiVerifiedObjectWriter $writer,
        private readonly WasabiGateway $gateway,
    ) {}

    public function write(string $relativePath, string $bytes): WrittenDerivativeObject
    {
        $source = fopen('php://temp', 'w+b');
        if ($source === false) {
            throw new DerivativeGenerationException('The derivative upload stream could not be prepared.');
        }
        fwrite($source, $bytes);
        rewind($source);

        try {
            $written = $this->writer->write(
                $this->prefix(),
                $relativePath,
                $source,
                strlen($bytes),
                hash('sha256', $bytes),
                'image/webp',
            );
        } catch (Throwable $exception) {
            throw new DerivativeGenerationException(
                'The Wasabi derivative could not be stored and verified.',
                previous: $exception,
            );
        } finally {
            fclose($source);
        }

        return new WrittenDerivativeObject(
            $relativePath,
            $written->bytes,
            $written->sha256,
            $written->versionId,
        );
    }

    public function removeCreated(WrittenDerivativeObject $object): void
    {
        if ($object->providerVersionId === null) {
            return;
        }

        $this->gateway->deleteVersion($this->prefix(), $object->relativePath, $object->providerVersionId);
    }

    private function prefix(): string
    {
        return (string) config('archive_providers.providers.wasabi.prefixes.archive_derivatives');
    }
}
