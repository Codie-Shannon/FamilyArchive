<?php

namespace App\Domain\Operations\Services;

use App\Domain\Operations\Contracts\ProductionProbe;
use App\Domain\Operations\Exceptions\ProductionVerificationException;
use App\Domain\Storage\Services\WasabiConnectionVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ProductionDeploymentVerifier
{
    public function __construct(
        private readonly ProductionReadiness $readiness,
        private readonly ProductionProbe $probe,
        private readonly WasabiConnectionVerifier $storage,
    ) {}

    public function verify(): object
    {
        $configuration = $this->readiness->configurationGates();

        try {
            if (in_array(false, $configuration, true)) {
                throw new ProductionVerificationException('Production configuration is incomplete.');
            }

            $url = (string) config('app.url');
            $runtime = $this->probe->run($url);
            if (in_array(false, $runtime, true)) {
                throw new ProductionVerificationException('A live production probe failed closed.');
            }

            $storage = $this->storage->verify();
            if ($storage->state !== 'verified') {
                throw new ProductionVerificationException('Private archive storage verification failed closed.');
            }

            $metrics = [...$configuration, ...$runtime, 'wasabi' => true];
            $eventId = (string) Str::uuid();

            DB::table('operational_events')->insert([
                'event_id' => $eventId,
                'type' => 'deployment',
                'severity' => 'info',
                'safe_summary' => 'Production HTTPS, durable application state, response hardening and private archive storage checks passed.',
                'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR),
                'resolved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('operational_events')->where('event_id', $eventId)->firstOrFail();
        } catch (Throwable $exception) {
            $this->recordFailure($configuration);

            throw new ProductionVerificationException(
                'Production verification failed closed. Review private application logs and deployment configuration.',
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<string, bool>  $configuration
     */
    private function recordFailure(array $configuration): void
    {
        try {
            DB::table('operational_events')->insert([
                'event_id' => (string) Str::uuid(),
                'type' => 'deployment',
                'severity' => 'critical',
                'safe_summary' => 'Production verification failed closed. No endpoint, account or credential details were recorded.',
                'metrics' => json_encode($configuration, JSON_THROW_ON_ERROR),
                'resolved_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // A database outage can prevent recording the failed deployment probe.
        }
    }
}
