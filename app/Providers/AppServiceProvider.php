<?php

namespace App\Providers;

use App\Domain\Archive\Contracts\NoOverwriteOriginalWriter;
use App\Domain\Archive\Services\LocalNoOverwriteOriginalWriter;
use App\Domain\Archive\Services\WasabiNoOverwriteOriginalWriter;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Services\LocalNoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Services\WasabiNoOverwriteDerivativeWriter;
use App\Domain\Intake\Contracts\NoOverwriteQuarantineWriter;
use App\Domain\Intake\Services\LocalNoOverwriteQuarantineWriter;
use App\Domain\Intake\Services\WasabiNoOverwriteQuarantineWriter;
use App\Domain\Operations\Contracts\ProductionProbe;
use App\Domain\Operations\Services\LiveProductionProbe;
use App\Domain\Storage\Contracts\WasabiGateway;
use App\Domain\Storage\Services\AwsWasabiGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WasabiGateway::class, AwsWasabiGateway::class);
        $this->app->singleton(ProductionProbe::class, LiveProductionProbe::class);

        if ((string) config('archive_providers.default', 'local') === 'wasabi') {
            $this->app->bind(NoOverwriteQuarantineWriter::class, WasabiNoOverwriteQuarantineWriter::class);
            $this->app->bind(NoOverwriteOriginalWriter::class, WasabiNoOverwriteOriginalWriter::class);
            $this->app->bind(NoOverwriteDerivativeWriter::class, WasabiNoOverwriteDerivativeWriter::class);

            return;
        }

        $this->app->bind(NoOverwriteQuarantineWriter::class, LocalNoOverwriteQuarantineWriter::class);
        $this->app->bind(NoOverwriteOriginalWriter::class, LocalNoOverwriteOriginalWriter::class);
        $this->app->bind(NoOverwriteDerivativeWriter::class, LocalNoOverwriteDerivativeWriter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHealthChecks();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function configureHealthChecks(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');

            $key = 'family-archive:health';

            try {
                Cache::put($key, 'ready', 15);

                if (Cache::get($key) !== 'ready') {
                    throw new \RuntimeException('The application cache health check failed.');
                }
            } finally {
                Cache::forget($key);
            }
        });
    }
}
