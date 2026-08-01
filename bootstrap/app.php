<?php

use App\Http\Middleware\EnsureAccountIsApproved;
use App\Http\Middleware\EnsureUserCanAccessWorkHub;
use App\Http\Middleware\EnsureUserCanManageFamilyOperations;
use App\Http\Middleware\EnsureUserCanManageTrustedIntake;
use App\Http\Middleware\EnsureUserIsOwner;
use App\Http\Middleware\PreventDemoWrites;
use App\Http\Middleware\ProductionSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ProductionSecurityHeaders::class);

        $middleware->alias([
            'demo.readonly' => PreventDemoWrites::class,
            'account.approved' => EnsureAccountIsApproved::class,
            'owner' => EnsureUserIsOwner::class,
            'family.operations' => EnsureUserCanManageFamilyOperations::class,
            'trusted.intake' => EnsureUserCanManageTrustedIntake::class,
            'work.access' => EnsureUserCanAccessWorkHub::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
