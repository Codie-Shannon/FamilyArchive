<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Contracts\ProductionProbe;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class LiveProductionProbe implements ProductionProbe
{
    public function run(string $applicationUrl): array
    {
        $database = $this->probeDatabase();
        $cache = $this->probeCache();
        $response = Http::timeout(15)
            ->accept('text/html')
            ->withOptions(['allow_redirects' => false])
            ->get(rtrim($applicationUrl, '/').'/up');

        $httpsResponse = $response->successful();
        $securityHeaders = $this->hasSecurityHeaders($response->headers());

        return [
            'https_response' => $httpsResponse,
            'database' => $database,
            'cache' => $cache,
            'security_headers' => $securityHeaders,
        ];
    }

    private function probeDatabase(): bool
    {
        DB::select('select 1');

        return true;
    }

    private function probeCache(): bool
    {
        $key = 'family-archive:production-probe:'.Str::uuid();

        try {
            Cache::put($key, 'verified', 30);

            return Cache::get($key) === 'verified';
        } finally {
            Cache::forget($key);
        }
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private function hasSecurityHeaders(array $headers): bool
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = $values;
        }

        foreach ([
            'strict-transport-security',
            'x-content-type-options',
            'x-frame-options',
            'referrer-policy',
            'permissions-policy',
            'content-security-policy',
        ] as $required) {
            if (! isset($normalized[$required]) || $normalized[$required] === []) {
                return false;
            }
        }

        if ($normalized['x-content-type-options'][0] !== 'nosniff') {
            throw new RuntimeException('The production response hardening contract was not satisfied.');
        }

        return true;
    }
}
