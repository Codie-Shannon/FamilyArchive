<?php

namespace App\Domain\Operations\Services;

use App\Domain\Storage\Services\ArchiveProviderReadiness;
use Illuminate\Support\Facades\DB;

final class ProductionReadiness
{
    public function __construct(
        private readonly ArchiveProviderReadiness $storage,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function configurationGates(): array
    {
        $url = (string) config('app.url');
        $database = (string) config('database.default');
        $databaseDriver = (string) config("database.connections.{$database}.driver");
        $cache = (string) config('cache.default');
        $session = (string) config('session.driver');
        $queue = (string) config('queue.default');
        $mail = (string) config('mail.default');
        $sameSite = strtolower((string) config('session.same_site'));
        $storage = $this->storage->report();

        return [
            'production_environment' => app()->environment('production'),
            'debug_disabled' => ! (bool) config('app.debug'),
            'application_key' => is_string(config('app.key')) && trim((string) config('app.key')) !== '',
            'https_origin' => parse_url($url, PHP_URL_SCHEME) === 'https'
                && is_string(parse_url($url, PHP_URL_HOST)),
            'durable_database' => ! in_array($databaseDriver, ['', 'sqlite'], true),
            'durable_cache' => in_array($cache, ['database', 'redis', 'memcached', 'dynamodb'], true),
            'durable_session' => in_array($session, ['database', 'redis', 'memcached', 'dynamodb'], true),
            'queued_jobs' => ! in_array($queue, ['', 'sync', 'null'], true),
            'mail_delivery' => ! in_array($mail, ['', 'array', 'log'], true),
            'secure_session_cookie' => (bool) config('session.secure')
                && (bool) config('session.encrypt')
                && in_array($sameSite, ['lax', 'strict'], true),
            'private_archive_storage' => $storage['provider'] === 'wasabi'
                && $storage['configured']
                && $storage['private'],
        ];
    }

    /**
     * @return array{
     *     state: 'verified'|'pending',
     *     ready: bool,
     *     gates: array<string, bool>,
     *     latest: object|null
     * }
     */
    public function report(): array
    {
        $latest = DB::table('operational_events')
            ->where('type', 'deployment')
            ->latest()
            ->first();

        $runtime = [];
        if ($latest !== null && is_string($latest->metrics)) {
            $decoded = json_decode($latest->metrics, true);
            if (is_array($decoded)) {
                foreach ($decoded as $name => $passed) {
                    if (is_string($name) && is_bool($passed)) {
                        $runtime[$name] = $passed;
                    }
                }
            }
        }

        $gates = [...$this->configurationGates(), ...$runtime];
        $verified = $latest !== null
            && $latest->resolved_at !== null
            && $gates !== []
            && ! in_array(false, $gates, true);

        return [
            'state' => $verified ? 'verified' : 'pending',
            'ready' => $verified,
            'gates' => $gates,
            'latest' => $latest,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function gateLabels(): array
    {
        return [
            'production_environment' => 'Production application mode',
            'debug_disabled' => 'Debug output disabled',
            'application_key' => 'Application encryption key',
            'https_origin' => 'HTTPS application origin',
            'durable_database' => 'Managed durable database',
            'durable_cache' => 'Durable shared cache',
            'durable_session' => 'Durable encrypted sessions',
            'queued_jobs' => 'Background job queue',
            'mail_delivery' => 'Transactional mail delivery',
            'secure_session_cookie' => 'Secure session cookie',
            'private_archive_storage' => 'Private Wasabi archive',
            'https_response' => 'Live HTTPS health response',
            'database' => 'Live database probe',
            'cache' => 'Live cache round trip',
            'security_headers' => 'Live response hardening',
            'wasabi' => 'Live private-storage verification',
        ];
    }
}
