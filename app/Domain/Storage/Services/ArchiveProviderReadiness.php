<?php

namespace App\Domain\Storage\Services;

use App\Domain\Storage\Exceptions\WasabiProviderException;

final class ArchiveProviderReadiness
{
    /**
     * @return array{
     *     provider: string,
     *     state: 'local'|'ready'|'incomplete'|'unsupported',
     *     configured: bool,
     *     private: bool,
     *     missing: list<string>
     * }
     */
    public function report(): array
    {
        $provider = (string) config('archive_providers.default', 'local');

        if ($provider === 'local') {
            return [
                'provider' => 'local',
                'state' => 'local',
                'configured' => true,
                'private' => true,
                'missing' => [],
            ];
        }

        if ($provider !== 'wasabi') {
            return [
                'provider' => 'unsupported',
                'state' => 'unsupported',
                'configured' => false,
                'private' => true,
                'missing' => ['provider'],
            ];
        }

        $wasabi = config('archive_providers.providers.wasabi');
        if (! is_array($wasabi)) {
            return [
                'provider' => 'wasabi',
                'state' => 'incomplete',
                'configured' => false,
                'private' => true,
                'missing' => ['configuration'],
            ];
        }

        $missing = [];
        foreach (['endpoint', 'region', 'bucket', 'key', 'secret'] as $field) {
            if (! is_string($wasabi[$field] ?? null) || trim((string) $wasabi[$field]) === '') {
                $missing[] = $field;
            }
        }

        $endpoint = (string) ($wasabi['endpoint'] ?? '');
        $endpointHost = parse_url($endpoint, PHP_URL_HOST);
        if (
            $endpoint !== ''
            && (
                parse_url($endpoint, PHP_URL_SCHEME) !== 'https'
                || ! is_string($endpointHost)
                || ! str_ends_with(strtolower($endpointHost), '.wasabisys.com')
            )
        ) {
            $missing[] = 'valid_https_endpoint';
        }

        $prefixes = $wasabi['prefixes'] ?? null;
        foreach (['archive_originals', 'archive_derivatives', 'archive_quarantine', 'archive_manifests', 'health'] as $prefix) {
            if (! is_array($prefixes) || ! is_string($prefixes[$prefix] ?? null) || trim((string) $prefixes[$prefix], '/') === '') {
                $missing[] = "prefix_{$prefix}";
            }
        }

        $missing = array_values(array_unique($missing));

        return [
            'provider' => 'wasabi',
            'state' => $missing === [] ? 'ready' : 'incomplete',
            'configured' => $missing === [],
            'private' => true,
            'missing' => $missing,
        ];
    }

    public function assertWasabiReady(): void
    {
        $report = $this->report();

        if ($report['provider'] !== 'wasabi' || ! $report['configured']) {
            throw new WasabiProviderException('Wasabi storage is not completely configured; the provider failed closed.');
        }
    }
}
