<?php

use Illuminate\Support\Facades\Route;

use App\Domains\Core\Controllers\AuthController;
use App\Domains\Core\Controllers\PaisController;
use App\Domains\Core\Controllers\EmpresaController;
use App\Domains\Core\Controllers\AnulacionController;
use App\Domains\Core\Controllers\UsuarioController;

use App\Domains\Comercial\Controllers\ClienteController;
use App\Domains\Comercial\Controllers\ProveedorController;
use App\Domains\Comercial\Controllers\FacturaController;
use App\Domains\Comercial\Controllers\CotizacionController;

use App\Domains\Contabilidad\Controllers\PlanCuentaController;
use App\Domains\Contabilidad\Controllers\AsientoContableController;
use App\Domains\Contabilidad\Controllers\ReporteController;
use App\Domains\Contabilidad\Controllers\ImpuestosController;

use App\Domains\Tesoreria\Controllers\BancoController;
use App\Domains\Tesoreria\Controllers\ConciliacionController;
use App\Domains\Tesoreria\Controllers\CuentaProveedorController;

use App\Domains\Activos\Controllers\ActivoFijoController;

use App\Domains\Inventario\Controllers\InventarioController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Gestión de usuarios y roles
    |--------------------------------------------------------------------------
    */
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::get('/usuarios/roles', [UsuarioController::class, 'roles']);
    Route::post('/usuarios/invitar', [UsuarioController::class, 'invitar']);
    Route::put('/usuarios/{id}/rol', [UsuarioController::class, 'actualizarRol']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'desvincular']);
    Route::post('/usuarios/roles', [UsuarioController::class, 'storeRol']);
    Route::put('/usuarios/roles/{id}', [UsuarioController::class, 'updateRol']);

    /*
    |--------------------------------------------------------------------------
    | Empresa - Perfil
    |--------------------------------------------------------------------------
    */
    Route::get('/empresas/perfil', [EmpresaController::class, 'perfil']);
    Route::put('/empresas/perfil', [EmpresaController::class, 'actualizarPerfil']);
    Route::post('/empresas/logo', [EmpresaController::class, 'subirLogo']);
    Route::get('/empresas/catalogo-bancos', [EmpresaController::class, 'catalogoBancos']);

    /*
    |--------------------------------------------------------------------------
    | Empresa - Cuentas Bancarias
    |--------------------------------------------------------------------------
    */
    Route::post('/empresas/bancos', [EmpresaController::class, 'agregarBanco']);
    Route::put('/empresas/bancos/{id}', [EmpresaController::class, 'actualizarBanco']);
    Route::delete('/empresas/bancos/{id}', [EmpresaController::class, 'eliminarBanco']);

    /*
    |--------------------------------------------------------------------------
    | Empresa - Centros de Costos
    |--------------------------------------------------------------------------
    */
    Route::get('/empresas/centros-costo', [EmpresaController::class, 'listarCentros']);
    Route::post('/empresas/centros-costo', [EmpresaController::class, 'agregarCentro']);
    Route::put('/empresas/centros-costo/{id}', [EmpresaController::class, 'actualizarCentro']);
    Route::delete('/empresas/centros-costo/{id}', [EmpresaController::class, 'eliminarCentro']);

    /*
    |--------------------------------------------------------------------------
    | Core
    |--------------------------------------------------------------------------
    */
    Route::get('/paises', [PaisController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Comercial - Clientes
    |--------------------------------------------------------------------------
    */
    Route::apiResource('clientes', ClienteController::class)
        ->except(['create', 'edit', 'show', 'update']);

    /*
    |--------------------------------------------------------------------------
    | Comercial - Proveedores
    |--------------------------------------------------------------------------
    */
    Route::get('/proveedores/catalogo', [ProveedorController::class, 'catalogo']);
    Route::get('/proveedores/ficha/{id}', [ProveedorController::class, 'ficha']);
    Route::apiResource('proveedores', ProveedorController::class)
        ->except(['create', 'edit', 'show', 'update', 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Comercial - Facturas
    |--------------------------------------------------------------------------
    */
    Route::get('/facturas/historial', [FacturaController::class, 'historial']);
    Route::get('/facturas/check', [FacturaController::class, 'check']);
    Route::apiResource('facturas', FacturaController::class)
        ->except(['create', 'edit', 'update']);

    /*
    |--------------------------------------------------------------------------
    | Comercial - Cotizaciones
    |--------------------------------------------------------------------------
    */
    Route::get('/cotizaciones/pdf/{id}', [CotizacionController::class, 'generarPdf']);
    Route::apiResource('cotizaciones', CotizacionController::class)
        ->except(['create', 'edit', 'show', 'update']);

    /*
    |--------------------------------------------------------------------------
    | Tesorería - Cuentas de Proveedores
    |--------------------------------------------------------------------------
    */
    Route::get('/cuentas-bancarias/proveedor/{proveedorId}', [CuentaProveedorController::class, 'index']);
    Route::post('/cuentas-bancarias', [CuentaProveedorController::class, 'store']);
    Route::delete('/cuentas-bancarias/{id}', [CuentaProveedorController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Tesorería - Bancos Propios y Conciliación
    |--------------------------------------------------------------------------
    */
    Route::get('/tesoreria/bancos-catalogo', [BancoController::class, 'catalogo']);
    Route::get('/tesoreria/cuentas-propias', [BancoController::class, 'cuentasEmpresa']);
    Route::post('/tesoreria/cuentas-propias', [BancoController::class, 'storeCuenta']);
    Route::post('/tesoreria/conciliar/factura-compra', [ConciliacionController::class, 'pagarFacturaCompra']);

    /*
    |--------------------------------------------------------------------------
    | Tesorería - Bancos y Conciliación
    |--------------------------------------------------------------------------
    */
    Route::post('/banco/nomina/pagar', [BancoController::class, 'pagarNomina']);
    Route::get('/banco/cuentas', [BancoController::class, 'cuentasEmpresa']);
    Route::get('/banco/cuentas-imputables', [PlanCuentaController::class, 'imputables']);

    /*
    |--------------------------------------------------------------------------
    | Tesorería - Mesa de Conciliación
    |--------------------------------------------------------------------------
    */
    Route::get('/banco/movimientos/pendientes/{idCuenta}', [ConciliacionController::class, 'movimientosPendientes']);
    Route::get('/banco/anticipos-pendientes', [ConciliacionController::class, 'anticiposPendientes']);
    Route::post('/banco/movimientos/conciliar', [ConciliacionController::class, 'conciliar']);
    Route::post('/banco/movimientos/conciliar-anticipo', [ConciliacionController::class, 'conciliarAnticipo']);

    /*
    |--------------------------------------------------------------------------
    | Contabilidad
    |--------------------------------------------------------------------------
    */
    Route::get('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'index']);
    Route::post('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'store']);
    Route::put('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'update']);
    Route::get('/contabilidad/asientos', [AsientoContableController::class, 'index']);
    Route::post('/contabilidad/asientos', [AsientoContableController::class, 'store']);
    Route::get('/contabilidad/libro-diario', [ReporteController::class, 'libroDiario']);
    Route::get('/contabilidad/reportes/libro-mayor', [ReporteController::class, 'libroMayor']);
    Route::get('/contabilidad/asientos/{id}', [AsientoContableController::class, 'show']);
    Route::post('/contabilidad/asiento-manual/avanzado', [AsientoContableController::class, 'storeAvanzado']);

    /*
    |--------------------------------------------------------------------------
    | Contabilidad - Formularios 29 y 22
    |--------------------------------------------------------------------------
    */
    Route::get('/impuestos/cierre-f29/simular/{mes}/{anio}', [ImpuestosController::class, 'simularF29']);
    Route::post('/impuestos/cierre-f29/ejecutar', [ImpuestosController::class, 'ejecutarF29']);
    Route::get('/renta/pre-calculo/{anio}', [ImpuestosController::class, 'preCalculoRenta']);
    Route::get('/renta/mapeo', [ImpuestosController::class, 'obtenerMapeo']);
    Route::post('/renta/mapeo', [ImpuestosController::class, 'guardarMapeo']);
    Route::delete('/renta/mapeo/{id}', [ImpuestosController::class, 'eliminarMapeo']);

    /*
    |--------------------------------------------------------------------------
    | Contabilidad - Anulaciones
    |--------------------------------------------------------------------------
    */
    Route::post('/anulacion/buscar', [AnulacionController::class, 'buscar']);
    Route::post('/anulacion/anular', [AnulacionController::class, 'anular']);

    /*
    |--------------------------------------------------------------------------
    | Activos Fijos
    |--------------------------------------------------------------------------
    */
    Route::get('/activos', [ActivoFijoController::class, 'index']);
    Route::get('/activos/pendientes', [ActivoFijoController::class, 'pendientes']);
    Route::post('/activos', [ActivoFijoController::class, 'store']);
    Route::get('/activos/parametros', [ActivoFijoController::class, 'parametros']);
    Route::post('/activos/depreciar-mes', [ActivoFijoController::class, 'depreciarMes']);

    /*
    |--------------------------------------------------------------------------
    | Activos Fijos - Proyectos
    |--------------------------------------------------------------------------
    */
    Route::get('/activos/proyectos', [ActivoFijoController::class, 'proyectos']);
    Route::post('/activos/proyectos', [ActivoFijoController::class, 'storeProyecto']);

    /*
    |--------------------------------------------------------------------------
    | Inventario
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventario')->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Fase 1 - Catálogos, productos, bodegas y stock base
        |--------------------------------------------------------------------------
        */
        Route::get('/catalogos', [InventarioController::class, 'catalogos']);

        Route::get('/productos', [InventarioController::class, 'index']);
        Route::post('/productos', [InventarioController::class, 'store']);

        Route::get('/bodegas', [InventarioController::class, 'bodegas']);
        Route::post('/bodegas', [InventarioController::class, 'storeBodega']);

        /*
        |--------------------------------------------------------------------------
        | Fase 2 - Movimientos de inventario y Kardex
        |--------------------------------------------------------------------------
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | Estos endpoints trabajan solo con stock físico, movimientos y Kardex.
        |
        */
        Route::get('/movimientos', [InventarioController::class, 'movimientos']);
        Route::post('/movimientos', [InventarioController::class, 'registrarMovimiento']);

        Route::get('/kardex', [InventarioController::class, 'kardex']);
        Route::get('/productos/{id}/kardex', [InventarioController::class, 'kardexProducto']);

        /*
        |--------------------------------------------------------------------------
        | Fase 3 - PMP y valorización
        |--------------------------------------------------------------------------
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | Estos endpoints consultan stock valorizado y resumen PMP.
        |
        */
        Route::get('/valorizacion', [InventarioController::class, 'valorizacion']);
        Route::get('/productos/{id}/valorizacion', [InventarioController::class, 'valorizacionProducto']);

        /*
        |--------------------------------------------------------------------------
        | Fase 4 - Mermas y ajustes críticos
        |--------------------------------------------------------------------------
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | Estos endpoints registran mermas, deterioros, pérdidas,
        | vencimientos y ajustes críticos sin lógica SII/DTE.
        |
        */
        Route::get('/ajustes-criticos/tipos', [InventarioController::class, 'tiposAjusteCritico']);
        Route::get('/ajustes-criticos', [InventarioController::class, 'ajustesCriticos']);
        Route::post('/ajustes-criticos', [InventarioController::class, 'registrarAjusteCritico']);
        Route::get('/ajustes-criticos/{id}', [InventarioController::class, 'verAjusteCritico']);

        /*
        |--------------------------------------------------------------------------
        | Fase 5 - Lotes, vencimientos y trazabilidad avanzada
        |--------------------------------------------------------------------------
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | Estos endpoints trabajan con trazabilidad granular por lote.
        |
        */
        Route::get('/lotes', [InventarioController::class, 'lotes']);
        Route::post('/lotes', [InventarioController::class, 'storeLote']);
        Route::get('/lotes/{id}/stock', [InventarioController::class, 'stockLote']);
        Route::get('/lotes/{id}', [InventarioController::class, 'showLote']);
        Route::put('/lotes/{id}', [InventarioController::class, 'updateLote']);

        Route::get('/productos/{id}/lotes', [InventarioController::class, 'lotesProducto']);

        /*
        |--------------------------------------------------------------------------
        | Fase 6 - Reservas y disponibilidad comprometida
        |--------------------------------------------------------------------------
        |
        | Inventario NO emite, gestiona ni prepara DTE.
        | Las reservas comprometen disponibilidad, pero no descuentan stock físico.
        | El stock físico solo se descuenta al consumir la reserva mediante salida real.
        |
        */
        Route::get('/disponibilidad', [InventarioController::class, 'disponibilidad']);
        Route::get('/productos/{id}/disponibilidad', [InventarioController::class, 'disponibilidadProducto']);

        Route::get('/reservas', [InventarioController::class, 'reservas']);
        Route::post('/reservas', [InventarioController::class, 'storeReserva']);
        Route::get('/reservas/{id}', [InventarioController::class, 'showReserva']);
        Route::post('/reservas/{id}/cancelar', [InventarioController::class, 'cancelarReserva']);
        Route::post('/reservas/{id}/liberar', [InventarioController::class, 'liberarReserva']);
        Route::post('/reservas/{id}/consumir', [InventarioController::class, 'consumirReserva']);

        /*
        |--------------------------------------------------------------------------
        | Fase 1 - Detalle y edición de productos
        |--------------------------------------------------------------------------
        |
        | Estas rutas dinámicas se dejan al final del grupo de Inventario para
        | mantener orden y evitar duplicados visuales con rutas específicas por fase.
        |
        */
        Route::get('/productos/{id}', [InventarioController::class, 'show']);
        Route::put('/productos/{id}', [InventarioController::class, 'update']);
    });
});