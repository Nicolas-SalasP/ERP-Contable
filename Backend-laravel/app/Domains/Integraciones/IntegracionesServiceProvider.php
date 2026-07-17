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
        Route::middleware(['api', 'integracion.api.key', 'throttle:integraciones-empresa'])
            ->prefix('api/integraciones/v1')
            ->group(__DIR__ . '/Routes/v1.php');

        // Administracion de keys: dentro del ERP, requiere sesion Sanctum + permiso.
        Route::middleware(['api', 'auth:sanctum', 'check.subscription'])
            ->prefix('api/integraciones/admin')
            ->group(__DIR__ . '/Routes/admin.php');
    }
}
