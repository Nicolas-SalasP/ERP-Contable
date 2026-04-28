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
use App\Domains\Core\Controllers\EmpresaController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Empresa - Perfil
    Route::get('/empresas/perfil', [EmpresaController::class, 'perfil']);
    Route::put('/empresas/perfil', [EmpresaController::class, 'actualizarPerfil']);
    Route::post('/empresas/logo', [EmpresaController::class, 'subirLogo']);
    Route::get('/empresas/catalogo-bancos', [EmpresaController::class, 'catalogoBancos']);

    // Empresa - Cuentas Bancarias
    Route::post('/empresas/bancos', [EmpresaController::class, 'agregarBanco']);
    Route::put('/empresas/bancos/{id}', [EmpresaController::class, 'actualizarBanco']);
    Route::delete('/empresas/bancos/{id}', [EmpresaController::class, 'eliminarBanco']);

    // Empresa - Centros de Costos
    Route::get('/empresas/centros-costo', [EmpresaController::class, 'listarCentros']);
    Route::post('/empresas/centros-costo', [EmpresaController::class, 'agregarCentro']);
    Route::put('/empresas/centros-costo/{id}', [EmpresaController::class, 'actualizarCentro']);
    Route::delete('/empresas/centros-costo/{id}', [EmpresaController::class, 'eliminarCentro']);

    // Core
    Route::get('/paises', [PaisController::class, 'index']);

    // Comercial - Clientes
    Route::apiResource('clientes', ClienteController::class)->except(['create', 'edit', 'show', 'update']);

    // Comercial - Proveedores
    Route::get('/proveedores/catalogo', [ProveedorController::class, 'catalogo']);
    Route::get('/proveedores/ficha/{id}', [ProveedorController::class, 'ficha']);
    Route::apiResource('proveedores', ProveedorController::class)->except(['create', 'edit', 'show', 'update', 'destroy']);

    // Comercial - Facturas
    Route::get('/facturas/historial', [FacturaController::class, 'historial']);
    Route::get('/facturas/check', [FacturaController::class, 'check']);
    Route::apiResource('facturas', FacturaController::class)->except(['create', 'edit', 'update']);

    // Comercial - Cotizaciones
    Route::get('/cotizaciones/pdf/{id}', [CotizacionController::class, 'generarPdf']);
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
    
    // Tesoreria - Bancos y Conciliacion
    Route::post('/banco/nomina/pagar', [BancoController::class, 'pagarNomina']);
    Route::get('/banco/cuentas', [BancoController::class, 'cuentasEmpresa']);
    Route::get('/banco/cuentas-imputables', [PlanCuentaController::class, 'imputables']);
    
    // Tesoreria - Mesa de Conciliacion
    Route::get('/banco/movimientos/pendientes/{idCuenta}', [ConciliacionController::class, 'movimientosPendientes']);
    Route::get('/banco/anticipos-pendientes', [ConciliacionController::class, 'anticiposPendientes']);
    Route::post('/banco/movimientos/conciliar', [ConciliacionController::class, 'conciliar']);
    Route::post('/banco/movimientos/conciliar-anticipo', [ConciliacionController::class, 'conciliarAnticipo']);

    // Contabilidad
    Route::get('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'index']);
    Route::post('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'store']);
    Route::put('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'update']);
    Route::get('/contabilidad/asientos', [AsientoContableController::class, 'index']);
    Route::post('/contabilidad/asientos', [AsientoContableController::class, 'store']);
    Route::get('/contabilidad/libro-diario', [ReporteController::class, 'libroDiario']);
    Route::get('/contabilidad/reportes/libro-mayor', [ReporteController::class, 'libroMayor']);
    Route::get('/contabilidad/asientos/{id}', [AsientoContableController::class, 'show']);

    // Contabilidad - Anulaciones
    Route::post('/anulacion/buscar', [\App\Domains\Core\Controllers\AnulacionController::class, 'buscar']);
    Route::post('/anulacion/anular', [\App\Domains\Core\Controllers\AnulacionController::class, 'anular']);
});