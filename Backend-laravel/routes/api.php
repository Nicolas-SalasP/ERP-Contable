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
use App\Domains\Comercial\Controllers\AnticipoProveedorController;
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
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Gestion de usuarios y roles
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::get('/usuarios/roles', [UsuarioController::class, 'roles']);
    Route::post('/usuarios/invitar', [UsuarioController::class, 'invitar']);
    Route::put('/usuarios/{id}/rol', [UsuarioController::class, 'actualizarRol']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'desvincular']);
    Route::post('/usuarios/roles', [UsuarioController::class, 'storeRol']);
    Route::put('/usuarios/roles/{id}', [UsuarioController::class, 'updateRol']);

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
    Route::get('/centros-costo', [EmpresaController::class, 'listarCentros']);
    Route::post('/empresas/centros-costo', [EmpresaController::class, 'agregarCentro']);
    Route::put('/empresas/centros-costo/{id}', [EmpresaController::class, 'actualizarCentro']);
    Route::delete('/empresas/centros-costo/{id}', [EmpresaController::class, 'eliminarCentro']);

    // Core
    Route::get('/paises', [PaisController::class, 'index']);

    // ---------------------------------------------------------------------
    // Comercial - Clientes
    // ---------------------------------------------------------------------
    Route::get('/clientes', [ClienteController::class, 'index']);
    Route::post('/clientes', [ClienteController::class, 'store']);
    Route::get('/clientes/{id}', [ClienteController::class, 'show']);
    Route::put('/clientes/{id}', [ClienteController::class, 'update']);
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);
    Route::put('/clientes/{id}/activar', [ClienteController::class, 'activar']);
    Route::patch('/clientes/{id}/reactivar', [ClienteController::class, 'reactivar']);

    // ---------------------------------------------------------------------
    // Comercial - Proveedores
    // ---------------------------------------------------------------------
    Route::get('/proveedores/catalogo', [ProveedorController::class, 'catalogo']);
    Route::get('/proveedores/ficha/{id}', [ProveedorController::class, 'ficha']);
    Route::post('/proveedores/anticipos', [ProveedorController::class, 'guardarAnticipo']);

    // Endpoints dedicados de anticipos a proveedores (con saldo disponible)
    Route::get('/anticipos-proveedores', [AnticipoProveedorController::class, 'index']);
    Route::post('/anticipos-proveedores', [AnticipoProveedorController::class, 'store']);
    Route::post('/anticipos-proveedores/{id}/aplicar', [AnticipoProveedorController::class, 'aplicar']);
    Route::post('/proveedores/{id}/cruzar-documentos', [ProveedorController::class, 'cruzarDocumentos']);
    Route::apiResource('proveedores', ProveedorController::class)->except(['show', 'destroy']);

    // ---------------------------------------------------------------------
    // Comercial - Facturas
    // ---------------------------------------------------------------------
    Route::get('/facturas/historial', [FacturaController::class, 'historial']);
    Route::get('/facturas/check', [FacturaController::class, 'check']);
    Route::get('/facturas/vencidas', [FacturaController::class, 'vencidas']);
    Route::get('/facturas/exportar/excel', [FacturaController::class, 'exportarExcel']);
    Route::get('/facturas/disponibles-proyectos', [FacturaController::class, 'disponiblesProyectos']);
    Route::apiResource('facturas', FacturaController::class)->except(['update']);
    Route::get('/facturas/{id}/asiento', [FacturaController::class, 'verAsiento']);
    Route::post('/facturas/{id}/reclasificar', [FacturaController::class, 'reclasificarAsiento']);
    Route::get('/facturas/{id}/auditoria', [FacturaController::class, 'auditoria']);
    Route::post('/facturas/{id}/pagar', [FacturaController::class, 'pagar']);
    Route::post('/facturas/{id}/anular', [FacturaController::class, 'anular']);
    Route::post('/facturas/{id}/vincular-proyecto', [FacturaController::class, 'vincularProyecto']);

    // ---------------------------------------------------------------------
    // Comercial - Cotizaciones
    // ---------------------------------------------------------------------
    Route::get('/cotizaciones/pdf/{id}', [CotizacionController::class, 'generarPdf']);
    Route::put('/cotizaciones/{id}/estado', [CotizacionController::class, 'actualizarEstado']);
    Route::patch('/cotizaciones/{id}/estado', [CotizacionController::class, 'actualizarEstado']);
    Route::post('/cotizaciones/{id}/facturar', [CotizacionController::class, 'facturar']);
    Route::apiResource('cotizaciones', CotizacionController::class)->except(['show', 'update']);
    Route::put('/cotizaciones/{id}', [CotizacionController::class, 'update']);

    // ---------------------------------------------------------------------
    // Tesoreria - Cuentas de Proveedores
    // ---------------------------------------------------------------------
    Route::get('/cuentas-bancarias/proveedor/{proveedorId}', [CuentaProveedorController::class, 'index']);
    Route::post('/cuentas-bancarias', [CuentaProveedorController::class, 'store']);
    Route::delete('/cuentas-bancarias/{id}', [CuentaProveedorController::class, 'destroy']);

    // ---------------------------------------------------------------------
    // Tesoreria - Bancos Propios y Conciliacion
    // ---------------------------------------------------------------------
    Route::get('/tesoreria/bancos-catalogo', [BancoController::class, 'catalogo']);
    Route::get('/tesoreria/cuentas-propias', [BancoController::class, 'cuentasEmpresa']);
    Route::post('/tesoreria/cuentas-propias', [BancoController::class, 'storeCuenta']);
    Route::post('/tesoreria/conciliar/factura-compra', [ConciliacionController::class, 'pagarFacturaCompra']);

    // Tesoreria - Bancos y Conciliacion
    Route::post('/banco/nomina/pagar', [BancoController::class, 'pagarNomina']);
    Route::get('/banco/cuentas', [BancoController::class, 'cuentasEmpresa']);
    Route::get('/banco/cuentas-imputables', [PlanCuentaController::class, 'imputables']);
    Route::post('/banco/ingreso-manual', [BancoController::class, 'ingresoManual']);
    Route::post('/banco/importar', [BancoController::class, 'importarCartola']);

    // Tesoreria - Movimientos
    Route::get('/banco/movimientos/pendientes/{idCuenta}', [ConciliacionController::class, 'movimientosPendientes']);
    Route::get('/banco/movimientos/{id}/sugerencias', [ConciliacionController::class, 'sugerencias']);
    Route::get('/banco/movimientos/{idCuenta}', [BancoController::class, 'movimientos']);

    // Tesoreria - Mesa de Conciliacion
    Route::get('/banco/anticipos-pendientes', [ConciliacionController::class, 'anticiposPendientes']);
    Route::post('/banco/movimientos/conciliar', [ConciliacionController::class, 'conciliar']);
    Route::post('/banco/movimientos/conciliar-anticipo', [ConciliacionController::class, 'conciliarAnticipo']);
    Route::post('/banco/movimientos/conciliar-facturas', [ConciliacionController::class, 'conciliarFacturas']);

    // ---------------------------------------------------------------------
    // Contabilidad - Plan de Cuentas
    // ---------------------------------------------------------------------
    Route::get('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'index']);
    Route::post('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'store']);
    Route::put('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'update']);
    Route::delete('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'destroy']);

    // Contabilidad - Asientos Contables
    Route::get('/contabilidad/asientos', [AsientoContableController::class, 'index']);
    Route::post('/contabilidad/asientos', [AsientoContableController::class, 'store']);
    Route::get('/contabilidad/asientos/{id}', [AsientoContableController::class, 'show']);
    Route::post('/contabilidad/asientos/{id}/reversar', [AsientoContableController::class, 'reversar']);
    Route::post('/contabilidad/asiento-manual/avanzado', [AsientoContableController::class, 'storeAvanzado']);

    // Contabilidad - Libros diarios y mayores
    Route::get('/contabilidad/libro-diario', [ReporteController::class, 'libroDiario']);
    Route::get('/contabilidad/reportes/libro-mayor', [ReporteController::class, 'libroMayor']);

    // Contabilidad - Formularios 29 y 22 (Renta)
    Route::get('/impuestos/cierre-f29/simular/{mes}/{anio}', [ImpuestosController::class, 'simularF29']);
    Route::post('/impuestos/cierre-f29/ejecutar', [ImpuestosController::class, 'ejecutarF29']);
    Route::get('/renta/pre-calculo/{anio}', [ImpuestosController::class, 'preCalculoRenta']);
    Route::get('/renta/mapeo', [ImpuestosController::class, 'obtenerMapeo']);
    Route::post('/renta/mapeo', [ImpuestosController::class, 'guardarMapeo']);
    Route::delete('/renta/mapeo/{id}', [ImpuestosController::class, 'eliminarMapeo']);

    // Contabilidad - Anulaciones
    Route::post('/anulacion/buscar', [AnulacionController::class, 'buscar']);
    Route::post('/anulacion/anular', [AnulacionController::class, 'anular']);

    // ---------------------------------------------------------------------
    // Activos Fijos
    // ---------------------------------------------------------------------
    Route::get('/activos', [ActivoFijoController::class, 'index']);
    Route::get('/activos/pendientes', [ActivoFijoController::class, 'pendientes']);
    Route::post('/activos', [ActivoFijoController::class, 'store']);
    Route::get('/activos/parametros', [ActivoFijoController::class, 'parametros']);
    Route::post('/activos/depreciar-mes', [ActivoFijoController::class, 'depreciarMes']);
    Route::put('/activos/{id}/baja', [ActivoFijoController::class, 'darDeBaja']);
    Route::put('/activos/{id}', [ActivoFijoController::class, 'update']);

    // Activos Fijos - Proyectos
    Route::get('/activos/proyectos/facturas-disponibles', [ActivoFijoController::class, 'facturasDisponibles']);
    Route::post('/activos/proyectos/{id}/facturas', [ActivoFijoController::class, 'imputarFactura']);
    Route::put('/activos/proyectos/{id}/activar', [ActivoFijoController::class, 'activarProyecto']);
    Route::put('/activos/proyectos/{id}', [ActivoFijoController::class, 'updateProyecto']);
    Route::delete('/activos/proyectos/{id}', [ActivoFijoController::class, 'deleteProyecto']);
    Route::delete('/activos/proyectos/{proyectoId}/facturas/{facturaId}', [ActivoFijoController::class, 'desvincularFactura']);
    Route::get('/activos/proyectos', [ActivoFijoController::class, 'proyectos']);
    Route::post('/activos/proyectos', [ActivoFijoController::class, 'storeProyecto']);
    Route::get('/activos/proyectos/{id}/analisis', [ActivoFijoController::class, 'analisisProyecto']);

    // ---------------------------------------------------------------------
    // Inventario, Bodegas y Movimientos (de dev / Slados)
    // ---------------------------------------------------------------------
    Route::prefix('inventario')->group(function () {
        Route::get('/catalogos', [InventarioController::class, 'catalogos']);

        Route::get('/productos', [InventarioController::class, 'index']);
        Route::post('/productos', [InventarioController::class, 'store']);

        Route::get('/bodegas', [InventarioController::class, 'bodegas']);
        Route::post('/bodegas', [InventarioController::class, 'storeBodega']);

        Route::get('/movimientos', [InventarioController::class, 'movimientos']);
        Route::post('/movimientos', [InventarioController::class, 'registrarMovimiento']);

        Route::get('/kardex', [InventarioController::class, 'kardex']);
        Route::get('/productos/{id}/kardex', [InventarioController::class, 'kardexProducto']);

        Route::get('/valorizacion', [InventarioController::class, 'valorizacion']);
        Route::get('/productos/{id}/valorizacion', [InventarioController::class, 'valorizacionProducto']);

        Route::get('/ajustes-criticos/tipos', [InventarioController::class, 'tiposAjusteCritico']);
        Route::get('/ajustes-criticos', [InventarioController::class, 'ajustesCriticos']);
        Route::post('/ajustes-criticos', [InventarioController::class, 'registrarAjusteCritico']);
        Route::get('/ajustes-criticos/{id}', [InventarioController::class, 'verAjusteCritico']);

        Route::get('/lotes', [InventarioController::class, 'lotes']);
        Route::post('/lotes', [InventarioController::class, 'storeLote']);
        Route::get('/lotes/{id}/stock', [InventarioController::class, 'stockLote']);
        Route::get('/lotes/{id}', [InventarioController::class, 'showLote']);
        Route::put('/lotes/{id}', [InventarioController::class, 'updateLote']);

        Route::get('/productos/{id}/lotes', [InventarioController::class, 'lotesProducto']);

        Route::get('/disponibilidad', [InventarioController::class, 'disponibilidad']);
        Route::get('/productos/{id}/disponibilidad', [InventarioController::class, 'disponibilidadProducto']);

        Route::get('/reservas', [InventarioController::class, 'reservas']);
        Route::post('/reservas', [InventarioController::class, 'storeReserva']);
        Route::get('/reservas/{id}', [InventarioController::class, 'showReserva']);
        Route::post('/reservas/{id}/cancelar', [InventarioController::class, 'cancelarReserva']);
        Route::post('/reservas/{id}/liberar', [InventarioController::class, 'liberarReserva']);
        Route::post('/reservas/{id}/consumir', [InventarioController::class, 'consumirReserva']);

        Route::get('/productos/{id}', [InventarioController::class, 'show']);
        Route::put('/productos/{id}', [InventarioController::class, 'update']);

        Route::get('/tomas-fisicas', [InventarioController::class, 'tomasFisicas']);
        Route::post('/tomas-fisicas', [InventarioController::class, 'storeTomaFisica']);
        Route::get('/tomas-fisicas/{id}', [InventarioController::class, 'showTomaFisica']);
        Route::post('/tomas-fisicas/{id}/iniciar', [InventarioController::class, 'iniciarTomaFisica']);
        Route::post('/tomas-fisicas/{id}/conteos', [InventarioController::class, 'registrarConteosTomaFisica']);
        Route::post('/tomas-fisicas/{id}/cerrar', [InventarioController::class, 'cerrarTomaFisica']);
        Route::post('/tomas-fisicas/{id}/ajustar', [InventarioController::class, 'ajustarTomaFisica']);
        Route::post('/tomas-fisicas/{id}/cancelar', [InventarioController::class, 'cancelarTomaFisica']);
    });
});
