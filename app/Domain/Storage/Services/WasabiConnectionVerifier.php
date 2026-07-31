<?php

namespace App\Domain\Storage\Services;

use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Exceptions\WasabiProviderException;
use App\Domain\Storage\Models\StorageProviderVerification;
use Throwable;

final class WasabiConnectionVerifier
{
    public function __construct(
        private readonly ArchiveProviderReadiness $readiness,
        private readonly WasabiGateway $gateway,
        private readonly WasabiVerifiedObjectWriter $writer,
    ) {}

    public function verify(): StorageProviderVerification
    {
        $configurationComplete = false;
        $bucketAccess = false;
        $versioningEnabled = false;
        $objectLockEnabled = false;
        $versionId = null;
        $relativePath = 'checks/'.now()->format('Y/m/d').'/'.bin2hex(random_bytes(16)).'.bin';
        $prefix = (string) config('archive_providers.providers.wasabi.prefixes.health');

        try {
            $this->readiness->assertWasabiReady();
            $configurationComplete = true;

            $protection = $this->gateway->bucketProtection();
            $bucketAccess = true;
            $versioningEnabled = $protection['versioning_enabled'];
            $objectLockEnabled = $protection['object_lock_enabled'];

            if (! $versioningEnabled || ! $objectLockEnabled) {
                throw new WasabiProviderException('Required Wasabi bucket protection is not enabled.');
            }

            $payload = random_bytes(4096);
            $source = fopen('php://temp', 'w+b');
            if ($source === false) {
                throw new WasabiProviderException('The provider verification stream could not be prepared.');
            }
            fwrite($source, $payload);
            rewind($source);

            try {
                $written = $this->writer->write(
                    $prefix,
                    $relativePath,
                    $source,
                    strlen($payload),
                    hash('sha256', $payload),
                );
                $versionId = $written->versionId;
            } finally {
                fclose($source);
            }

            $this->gateway->deleteVersion($prefix, $relativePath, $versionId);

            return StorageProviderVerification::query()->create([
                'provider' => 'wasabi',
                'state' => 'verified',
                'configuration_complete' => true,
                'bucket_access' => true,
                'versioning_enabled' => true,
                'object_lock_enabled' => true,
                'write_read_delete_verified' => true,
                'safe_summary' => 'Private Wasabi write, exact-version readback, SHA-256 verification and health cleanup passed.',
                'checked_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($versionId !== null) {
                try {
                    $this->gateway->deleteVersion($prefix, $relativePath, $versionId);
                } catch (Throwable) {
                    // The failed health object remains isolated under the health prefix.
                }
            }

            StorageProviderVerification::query()->create([
                'provider' => 'wasabi',
                'state' => 'failed',
                'configuration_complete' => $configurationComplete,
                'bucket_access' => $bucketAccess,
                'versioning_enabled' => $versioningEnabled,
                'object_lock_enabled' => $objectLockEnabled,
                'write_read_delete_verified' => false,
                'safe_summary' => 'Wasabi verification failed closed. Review private application logs and provider configuration.',
                'checked_at' => now(),
            ]);

            throw new WasabiProviderException(
                'Wasabi verification failed closed. No credential or bucket details were displayed.',
                previous: $exception,
            );
        }
    }
}
