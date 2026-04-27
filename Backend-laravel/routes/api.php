<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Core\Controllers\AuthController;
use App\Domains\Core\Controllers\PaisController;
use App\Domains\Comercial\Controllers\ClienteController;
use App\Domains\Comercial\Controllers\ProveedorController;
use App\Domains\Comercial\Controllers\FacturaController;
use App\Domains\Comercial\Controllers\CotizacionController;
use App\Domains\Contabilidad\Controllers\PlanCuentaController;
use App\Domains\Contabilidad\Controllers\AsientoContableController;
use App\Domains\Contabilidad\Controllers\ReporteController;
use App\Domains\Tesoreria\Controllers\BancoController;
use App\Domains\Tesoreria\Controllers\ConciliacionController;
use App\Domains\Tesoreria\Controllers\CuentaProveedorController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Core
    Route::get('/paises', [PaisController::class, 'index']);

    // Comercial - Clientes
    Route::apiResource('clientes', ClienteController::class)->except(['create', 'edit', 'show', 'update']);

    // Comercial - Proveedores
    Route::get('/proveedores/catalogo', [ProveedorController::class, 'catalogo']);
    Route::apiResource('proveedores', ProveedorController::class)->except(['create', 'edit', 'show', 'update', 'destroy']);

    // Comercial - Facturas
    Route::get('/facturas/historial', [FacturaController::class, 'historial']);
    Route::get('/facturas/check', [FacturaController::class, 'check']);
    Route::apiResource('facturas', FacturaController::class)->except(['create', 'edit', 'update']);

    // Comercial - Cotizaciones
    Route::apiResource('cotizaciones', CotizacionController::class)->except(['create', 'edit', 'show', 'update']);

    // Tesoreria - Cuentas de Proveedores
    Route::get('/cuentas-bancarias/proveedor/{proveedorId}', [CuentaProveedorController::class, 'index']);
    Route::post('/cuentas-bancarias', [CuentaProveedorController::class, 'store']);
    Route::delete('/cuentas-bancarias/{id}', [CuentaProveedorController::class, 'destroy']);

    // Tesoreria - Bancos Propios y Conciliacion
    Route::get('/tesoreria/bancos-catalogo', [BancoController::class, 'catalogo']);
    Route::get('/tesoreria/cuentas-propias', [BancoController::class, 'cuentasEmpresa']);
    Route::post('/tesoreria/cuentas-propias', [BancoController::class, 'storeCuenta']);
    Route::post('/tesoreria/conciliar/factura-compra', [ConciliacionController::class, 'pagarFacturaCompra']);
    Route::get('/banco/cuentas', [BancoController::class, 'cuentasEmpresa']);

    // Contabilidad
    Route::get('/contabilidad/cuentas', [PlanCuentaController::class, 'index']);
    Route::post('/contabilidad/cuentas', [PlanCuentaController::class, 'store']);
    Route::get('/contabilidad/asientos', [AsientoContableController::class, 'index']);
    Route::post('/contabilidad/asientos', [AsientoContableController::class, 'store']);
    Route::get('/contabilidad/reportes/libro-mayor', [ReporteController::class, 'libroMayor']);
});