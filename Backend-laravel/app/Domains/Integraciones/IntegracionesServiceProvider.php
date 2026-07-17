<?php

namespace App\Domains\Integraciones;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IntegracionesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // API publica para terceros (Tenri-Web-Page, WordPress, etc.): sin auth:sanctum, sin
        // EmpresaScope activo -> auth por API-key + scopes, throttle propio por empresa.
        // El throttle va ANTES de la auth: si corriera despues, un token invalido nunca
        // consumiria cupo (AutenticarApiKey corta con 401 sin llamar next()), permitiendo
        // enumeracion de tokens sin limite. El limiter ya tiene fallback a IP si el token
        // es invalido/falta (ver RateLimiter::for('integraciones-empresa') en AppServiceProvider).
        Route::middleware(['api', 'throttle:integraciones-empresa', 'integracion.api.key'])
            ->prefix('api/integraciones/v1')
            ->group(__DIR__ . '/Routes/v1.php');

        // Administracion de keys: dentro del ERP, requiere sesion Sanctum + permiso + suscripcion
        // escribible (emitir/rotar/revocar keys son escrituras, igual que el resto del proyecto).
        Route::middleware(['api', 'auth:sanctum', 'check.subscription'])
            ->prefix('api/integraciones/admin')
            ->group(__DIR__ . '/Routes/admin.php');
    }
}
