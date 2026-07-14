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
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sin proxy/CDN delante en prod (hosting DirectAdmin directo): no confiar en
        // ningun X-Forwarded-For, o cualquiera podria spoofear su IP y evadir el
        // rate limit de login. REMOTE_ADDR ya es la IP real del cliente.
        // API-only: no existe ruta 'login'. Sin esto, Laravel intenta route('login') al
        // armar el redirect de invitado (requests sin header Accept: application/json)
        // y explota con RouteNotFoundException -> 500 en vez de un 401 limpio.
        $middleware->redirectGuestsTo(fn () => null);
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

        // Red de seguridad final: cualquier excepción sin render() propio ni capturada por un
        // controller (ej. error de config/libreria de terceros, como CIPHERSWEET_KEY faltante)
        // no debe filtrar el mensaje crudo del framework/libreria al cliente. Las excepciones de
        // dominio (CoreException, RrhhException, etc.) tienen su propio render() y Laravel lo usa
        // ANTES de llegar aqui, asi que este catch-all solo atrapa lo verdaderamente inesperado.
        // IMPORTANTE: se excluyen explicitamente ValidationException y cualquier
        // HttpExceptionInterface (404/403/405/429/etc.) porque esas YA traen el status/mensaje
        // correcto para el cliente — atraparlas aqui convertiria, por ejemplo, todo 422 de
        // validacion en un 500 generico en toda la API. Con APP_DEBUG=true se deja pasar
        // (return null) para no estorbar el debug local.
        $exceptions->render(function (Throwable $e, $request) {
            if (config('app.debug')
                || ! $request->expectsJson()
                || $e instanceof ValidationException
                || $e instanceof HttpExceptionInterface
            ) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar la solicitud. Si el problema persiste, contacta a soporte.',
            ], 500);
        });
    })->create();
