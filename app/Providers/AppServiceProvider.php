<?php

namespace App\Providers;

use App\Domain\Archive\Contracts\NoOverwriteOriginalWriter;
use App\Domain\Archive\Services\LocalNoOverwriteOriginalWriter;
use App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter;
use App\Domain\Derivatives\Services\LocalNoOverwriteDerivativeWriter;
use App\Domain\Intake\Contracts\NoOverwriteQuarantineWriter;
use App\Domain\Intake\Services\LocalNoOverwriteQuarantineWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
}
