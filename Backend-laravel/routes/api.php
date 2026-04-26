<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Core\Controllers\AuthController;
use App\Domains\Contabilidad\Controllers\PlanCuentaController;
use App\Domains\Contabilidad\Controllers\AsientoContableController;
use App\Domains\Comercial\Controllers\ClienteController;
use App\Domains\Comercial\Controllers\ProveedorController;
use App\Domains\Comercial\Controllers\FacturaController;
use App\Domains\Tesoreria\Controllers\ConciliacionController;
use App\Domains\Contabilidad\Controllers\ReporteController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Contabilidad
        Route::get('/contabilidad/cuentas', [PlanCuentaController::class, 'index']);
        Route::get('/contabilidad/asientos', [AsientoContableController::class, 'index']);
        Route::post('/contabilidad/asientos', [AsientoContableController::class, 'store']);

        // Conciliación Bancaria
        Route::post('/tesoreria/conciliar/factura-compra', [ConciliacionController::class, 'pagarFacturaCompra']);

        // Dominio Comercial
        Route::apiResource('clientes', ClienteController::class)->except(['create', 'edit']);
        Route::apiResource('proveedores', ProveedorController::class)->except(['create', 'edit']);
        Route::apiResource('facturas', FacturaController::class)->except(['create', 'edit', 'update']);

        // Reportes Contables
        Route::get('/contabilidad/reportes/libro-mayor', [ReporteController::class, 'libroMayor']);
        
    });

});