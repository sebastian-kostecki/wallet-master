<?php

use App\Http\Middleware\ConfigureInertiaSsr;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRegistrationEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApplicationLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'registration.enabled' => EnsureRegistrationEnabled::class,
        ]);

        $middleware->web(prepend: [
            ...(env('APP_ENV') === 'production' ? [SecurityHeaders::class] : []),
            SetApplicationLocale::class,
        ]);

        $middleware->web(append: [
            ConfigureInertiaSsr::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
