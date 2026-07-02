<?php

use App\Http\Middleware\AgregarRequestId;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureSubscriptionWritable;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackUltimoAcceso;
use App\Http\Middleware\VerifyWebApiKey;
use App\Support\MensajeErrorGenerico;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->append(AgregarRequestId::class);
        $middleware->alias([
            'web.api.key' => VerifyWebApiKey::class,
            'check.subscription' => CheckSubscription::class,
            'subscription.writable' => EnsureSubscriptionWritable::class,
            'permiso' => EnsureUserHasPermission::class,
            'track.ultimo.acceso' => TrackUltimoAcceso::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        });

        // Red de seguridad: cualquier QueryException no capturada localmente por un
        // controller/servicio no debe filtrar SQL crudo (tabla, columnas, valores) al cliente.
        $exceptions->render(function (QueryException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => MensajeErrorGenerico::desde($e),
                ], 500);
            }
        });
    })->create();
