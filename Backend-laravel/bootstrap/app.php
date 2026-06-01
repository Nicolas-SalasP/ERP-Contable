<?php

use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\TrackUltimoAcceso;
use App\Http\Middleware\VerifyWebApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'web.api.key'         => VerifyWebApiKey::class,
            'check.subscription'  => CheckSubscription::class,
            'track.ultimo.acceso' => TrackUltimoAcceso::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
