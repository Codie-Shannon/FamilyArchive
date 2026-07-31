<?php

use App\Domain\Operations\Contracts\ProductionProbe;
use App\Domain\Operations\Services\ProductionDeploymentVerifier;
use App\Domain\Operations\Services\ProductionReadiness;
use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Exceptions\WasabiObjectExistsException;
use App\Domain\Storage\Services\ArchiveProviderReadiness;
use App\Domain\Storage\Services\WasabiConnectionVerifier;
use App\Domain\Storage\Services\WasabiVerifiedObjectWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function configureGroup15Production(): void
{
    app()->detectEnvironment(fn (): string => 'production');

    config()->set('app.debug', false);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('app.url', 'https://family-archive.example');
    config()->set('database.connections.sqlite.driver', 'mysql');
    config()->set('cache.default', 'database');
    config()->set('session.driver', 'database');
    config()->set('session.secure', true);
    config()->set('session.encrypt', true);
    config()->set('session.same_site', 'lax');
    config()->set('queue.default', 'database');
    config()->set('mail.default', 'smtp');
    config()->set('archive_providers.default', 'wasabi');
    config()->set('archive_providers.providers.wasabi', [
        'driver' => 's3',
        'endpoint' => 'https://s3.ap-southeast-2.wasabisys.com',
        'region' => 'ap-southeast-2',
        'bucket' => 'private-production-bucket',
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

function group15Gateway(): WasabiGateway
{
    return new class implements WasabiGateway
    {
        /** @var array<string, string> */
        public array $objects = [];

        public function putStreamIfAbsent(
            string $prefix,
            string $relativePath,
            $stream,
            int $contentLength,
            string $contentMd5,
            string $contentType = 'application/octet-stream',
        ): string {
            $key = trim($prefix, '/').'/'.ltrim($relativePath, '/');
            if (isset($this->objects[$key])) {
                throw new WasabiObjectExistsException('Object exists.');
            }

            $bytes = stream_get_contents($stream);
            if (! is_string($bytes) || strlen($bytes) !== $contentLength) {
                throw new RuntimeException('Invalid fake upload.');
            }

            $this->objects[$key] = $bytes;

            return 'version-1';
        }

        public function readStream(string $prefix, string $relativePath, ?string $versionId = null)
        {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $this->objects[trim($prefix, '/').'/'.ltrim($relativePath, '/')]);
            rewind($stream);

            return $stream;
        }

        public function objectExists(string $prefix, string $relativePath): bool
        {
            return isset($this->objects[trim($prefix, '/').'/'.ltrim($relativePath, '/')]);
        }

        public function deleteVersion(string $prefix, string $relativePath, string $versionId): void
        {
            unset($this->objects[trim($prefix, '/').'/'.ltrim($relativePath, '/')]);
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

it('presents a product-specific public home without starter-kit links', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Preserve the story.')
        ->assertSee('Protect the source.')
        ->assertSee('Explore the public archive')
        ->assertDontSee('Deploy now')
        ->assertDontSee('Laravel documentation')
        ->assertDontSee('livewire-starter-kit');
});

it('adds hardened response headers and checks database and cache health', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('app.url', 'https://family-archive.example');

    $response = $this->withServerVariables(['HTTPS' => 'on'])->get('/up');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

it('records live production proof only after every safe probe passes', function (): void {
    configureGroup15Production();

    $gateway = group15Gateway();
    $probe = new class implements ProductionProbe
    {
        public function run(string $applicationUrl): array
        {
            return [
                'https_response' => true,
                'database' => true,
                'cache' => true,
                'security_headers' => true,
            ];
        }
    };
    $storage = new WasabiConnectionVerifier(
        app(ArchiveProviderReadiness::class),
        $gateway,
        new WasabiVerifiedObjectWriter($gateway),
    );
    $verifier = new ProductionDeploymentVerifier(
        app(ProductionReadiness::class),
        $probe,
        $storage,
    );

    $event = $verifier->verify();
    $report = app(ProductionReadiness::class)->report();

    expect($event->resolved_at)->not->toBeNull()
        ->and($event->safe_summary)->toContain('Production HTTPS')
        ->and($event->safe_summary)->not->toContain('family-archive.example')
        ->and($event->safe_summary)->not->toContain('private-production-bucket')
        ->and($event->safe_summary)->not->toContain('TEST-SECRET-KEY')
        ->and($report['ready'])->toBeTrue()
        ->and($report['state'])->toBe('verified')
        ->and($gateway->objects)->toBeEmpty();
});

it('fails closed without creating resolved deployment proof when configuration is incomplete', function (): void {
    $this->artisan('archive:production-verify')->assertFailed();

    expect(DB::table('operational_events')
        ->where('type', 'deployment')
        ->whereNotNull('resolved_at')
        ->exists())->toBeFalse();
});

it('keeps production readiness inside the verified owner boundary', function (): void {
    $viewer = User::factory()->create();
    $owner = User::factory()->create(['role' => 'owner']);

    $this->get(route('admin.production-readiness'))->assertRedirect(route('login'));
    $this->actingAs($viewer)->get(route('admin.production-readiness'))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.production-readiness'))
        ->assertOk()
        ->assertSee('Production readiness')
        ->assertSee('Live production gates')
        ->assertSee('No live deployment verification has been recorded.')
        ->assertDontSee('private-production-bucket')
        ->assertDontSee('TEST-SECRET-KEY');
});
