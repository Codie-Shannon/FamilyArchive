<?php

namespace App\Domain\Storage\Services;

final class ArchiveProviderConfiguration
{
    /** @return array{provider: string, configured: bool, missing: list<string>, visibility: string} */
    public function status(?string $provider = null): array
    {
        $provider ??= (string) config('archive_providers.default');
        $config = (array) config("archive_providers.providers.{$provider}", []);

        if ($provider === 'local') {
            return [
                'provider' => 'local',
                'configured' => true,
                'missing' => [],
                'visibility' => 'private',
            ];
        }

        $required = ['endpoint', 'region', 'bucket', 'key', 'secret'];
        $missing = array_values(array_filter(
            $required,
            fn (string $key): bool => blank($config[$key] ?? null),
        ));

        return [
            'provider' => $provider,
            'configured' => $missing === [],
            'missing' => $missing,
            'visibility' => 'private',
        ];
    }
}
