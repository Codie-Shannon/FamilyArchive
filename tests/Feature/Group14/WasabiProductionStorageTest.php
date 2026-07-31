<?php

use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Exceptions\WasabiObjectExistsException;
use App\Domain\Storage\Models\StorageProviderVerification;
use App\Domain\Storage\Services\ArchiveProviderReadiness;
use App\Domain\Storage\Services\WasabiConnectionVerifier;
use App\Domain\Storage\Services\WasabiLeastPrivilegePolicy;
use App\Domain\Storage\Services\WasabiVerifiedObjectWriter;
use App\Models\User;

function configureGroup14Wasabi(): void
{
    config()->set('archive_providers.default', 'wasabi');
    config()->set('archive_providers.providers.wasabi', [
        'driver' => 's3',
        'endpoint' => 'https://s3.ap-southeast-2.wasabisys.com',
        'region' => 'ap-southeast-2',
        'bucket' => 'private-test-bucket',
        'key' => 'TEST-ACCESS-KEY',
        'secret' => 'TEST-SECRET-KEY',
        'use_path_style_endpoint' => true,
        'visibility' => 'private',
        'prefixes' => [
            'archive_originals' => 'archive/originals',
            'archive_derivatives' => 'archive/derivatives',
            'archive_quarantine' => 'archive/quarantine',
            'archive_manifests' => 'archive/manifests',
            'health' => 'archive/health',
        ],
    ]);
}

function group14Gateway(): WasabiGateway
{
    return new class implements WasabiGateway
    {
        /** @var array<string, string> */
        public array $objects = [];

        /** @var list<string> */
        public array $deletedVersions = [];

        public function putStreamIfAbsent(
            string $prefix,
            string $relativePath,
            $stream,
            int $contentLength,
            string $contentMd5,
            string $contentType = 'application/octet-stream',
        ): string {
            $key = trim($prefix, '/').'/'.ltrim($relativePath, '/');
            if (array_key_exists($key, $this->objects)) {
                throw new WasabiObjectExistsException('Object exists.');
            }

            $bytes = stream_get_contents($stream);
            if (! is_string($bytes) || strlen($bytes) !== $contentLength) {
                throw new RuntimeException('Invalid fake upload.');
            }

            $this->objects[$key] = $bytes;

            return 'version-'.count($this->objects);
        }

        public function readStream(string $prefix, string $relativePath, ?string $versionId = null)
        {
            $key = trim($prefix, '/').'/'.ltrim($relativePath, '/');
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $this->objects[$key]);
            rewind($stream);

            return $stream;
        }

        public function objectExists(string $prefix, string $relativePath): bool
        {
            return array_key_exists(trim($prefix, '/').'/'.ltrim($relativePath, '/'), $this->objects);
        }

        public function deleteVersion(string $prefix, string $relativePath, string $versionId): void
        {
            unset($this->objects[trim($prefix, '/').'/'.ltrim($relativePath, '/')]);
            $this->deletedVersions[] = $versionId;
        }

        public function bucketProtection(): array
        {
            return [
                'versioning_enabled' => true,
                'object_lock_enabled' => true,
            ];
        }
    };
}

it('fails closed when required Wasabi configuration is missing', function (): void {
    config()->set('archive_providers.default', 'wasabi');
    config()->set('archive_providers.providers.wasabi.key', null);
    config()->set('archive_providers.providers.wasabi.secret', null);

    $report = app(ArchiveProviderReadiness::class)->report();

    expect($report['state'])->toBe('incomplete')
        ->and($report['configured'])->toBeFalse()
        ->and($report['missing'])->toContain('key', 'secret')
        ->and(json_encode($report))->not->toContain('TEST-SECRET');
});

it('writes only when absent and verifies the exact returned version', function (): void {
    configureGroup14Wasabi();
    $gateway = group14Gateway();
    $writer = new WasabiVerifiedObjectWriter($gateway);
    $source = fopen('php://temp', 'w+b');
    fwrite($source, 'verified private archive bytes');
    rewind($source);

    $written = $writer->write('archive/health', 'checks/test.bin', $source);
    fclose($source);

    expect($written->bytes)->toBe(30)
        ->and($written->sha256)->toBe(hash('sha256', 'verified private archive bytes'))
        ->and($written->versionId)->toBe('version-1')
        ->and($gateway->objectExists('archive/health', 'checks/test.bin'))->toBeTrue();

    $duplicate = fopen('php://temp', 'w+b');
    fwrite($duplicate, 'verified private archive bytes');
    rewind($duplicate);

    expect(fn () => $writer->write('archive/health', 'checks/test.bin', $duplicate))
        ->toThrow(WasabiObjectExistsException::class);
    fclose($duplicate);
});

it('records a safe live verification without provider identifiers', function (): void {
    configureGroup14Wasabi();
    $gateway = group14Gateway();
    $verifier = new WasabiConnectionVerifier(
        app(ArchiveProviderReadiness::class),
        $gateway,
        new WasabiVerifiedObjectWriter($gateway),
    );

    $record = $verifier->verify();

    expect($record->state)->toBe('verified')
        ->and($record->bucket_access)->toBeTrue()
        ->and($record->versioning_enabled)->toBeTrue()
        ->and($record->object_lock_enabled)->toBeTrue()
        ->and($record->write_read_delete_verified)->toBeTrue()
        ->and($record->safe_summary)->not->toContain('private-test-bucket')
        ->and($record->safe_summary)->not->toContain('TEST-ACCESS-KEY')
        ->and($gateway->objects)->toBeEmpty()
        ->and($gateway->deletedVersions)->toHaveCount(1);
});

it('generates a private least privilege policy with no original delete permission', function (): void {
    configureGroup14Wasabi();
    $policy = app(WasabiLeastPrivilegePolicy::class)->forBucket('private-test-bucket');
    $json = json_encode($policy, JSON_THROW_ON_ERROR);
    $deleteStatement = collect($policy['Statement'])->firstWhere('Sid', 'CleanOnlyReplaceableAndHealthVersions');

    expect($json)->toContain('s3:PutObject')
        ->toContain('s3:GetObjectVersion')
        ->toContain('s3:DeleteObjectVersion')
        ->not->toContain('s3:PutBucket')
        ->not->toContain('s3:PutObjectAcl')
        ->not->toContain('s3:ListBucket')
        ->not->toContain('"public"')
        ->and($deleteStatement['Resource'])->not->toContain('archive/originals/*')
        ->and($deleteStatement['Resource'])->not->toContain('archive/manifests/*');
});

it('shows only safe provider facts on the owner storage screen', function (): void {
    configureGroup14Wasabi();
    StorageProviderVerification::query()->create([
        'provider' => 'wasabi',
        'state' => 'verified',
        'configuration_complete' => true,
        'bucket_access' => true,
        'versioning_enabled' => true,
        'object_lock_enabled' => true,
        'write_read_delete_verified' => true,
        'safe_summary' => 'Private provider verification passed.',
        'checked_at' => now(),
    ]);
    $owner = User::factory()->create(['role' => 'owner']);

    $this->actingAs($owner)->get(route('admin.archive-storage'))
        ->assertOk()
        ->assertSee('Production provider boundary')
        ->assertSee('Object Lock')
        ->assertSee('Capability verified')
        ->assertSee('Private provider verification passed.')
        ->assertSee('Read-only migration plan')
        ->assertSee('Local deletion')
        ->assertSee('Unavailable')
        ->assertDontSee('private-test-bucket')
        ->assertDontSee('TEST-ACCESS-KEY')
        ->assertDontSee('TEST-SECRET-KEY')
        ->assertDontSee('s3.ap-southeast-2.wasabisys.com');
});
