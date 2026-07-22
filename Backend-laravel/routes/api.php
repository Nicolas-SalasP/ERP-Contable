<?php

use App\Domains\Activos\Controllers\ActivoFijoController;
use App\Domains\Alertas\Controllers\AlertaController as AlertaGenericaController;
use App\Domains\Comercial\Controllers\AnticipoClienteController;
use App\Domains\Comercial\Controllers\AnticipoProveedorController;
use App\Domains\Comercial\Controllers\ClienteController;
use App\Domains\Comercial\Controllers\CotizacionController;
use App\Domains\Comercial\Controllers\DocumentoAdjuntoController;
use App\Domains\Comercial\Controllers\FacturaController;
use App\Domains\Comercial\Controllers\HonorariosController;
use App\Domains\Comercial\Controllers\OrdenCompraController;
use App\Domains\Comercial\Controllers\ProveedorController;
use App\Domains\Contabilidad\Controllers\ApAgingController;
use App\Domains\Contabilidad\Controllers\ArAgingController;
use App\Domains\Contabilidad\Controllers\AsientoContableController;
use App\Domains\Contabilidad\Controllers\Dj1835Controller;
use App\Domains\Contabilidad\Controllers\Dj1837Controller;
use App\Domains\Contabilidad\Controllers\Dj1879Controller;
use App\Domains\Contabilidad\Controllers\Dj1887Controller;
use App\Domains\Contabilidad\Controllers\Dj1926Controller;
use App\Domains\Contabilidad\Controllers\Dj1947Controller;
use App\Domains\Contabilidad\Controllers\ImpuestosController;
use App\Domains\Contabilidad\Controllers\LibroComprasVentasController;
use App\Domains\Contabilidad\Controllers\PeriodoContableController;
use App\Domains\Contabilidad\Controllers\PlanCuentaController;
use App\Domains\Contabilidad\Controllers\ReporteController;
use App\Domains\Core\Controllers\AnulacionController;
use App\Domains\Core\Controllers\AuditoriaController;
use App\Domains\Core\Controllers\AuthController;
use App\Domains\Core\Controllers\DashboardResumenController;
use App\Domains\Core\Controllers\EmpresaCambioController;
use App\Domains\Core\Controllers\EmpresaController;
use App\Domains\Core\Controllers\HealthController;
use App\Domains\Core\Controllers\IncidenteSeguridadController;
use App\Domains\Core\Controllers\Internal\AdminEmpresasController;
use App\Domains\Core\Controllers\Internal\WebProvisioningController;
use App\Domains\Core\Controllers\PaisController;
use App\Domains\Core\Controllers\PrivacidadController;
use App\Domains\Core\Controllers\PropietariosController;
use App\Domains\Core\Controllers\UsuarioController;
use App\Domains\Core\Support\ModuloPermisos;
use App\Domains\CorreccionMonetaria\Controllers\CorreccionMonetariaController;
use App\Domains\Inventario\Controllers\AjusteCriticoController;
use App\Domains\Inventario\Controllers\AlertaController;
use App\Domains\Inventario\Controllers\BodegaController;
use App\Domains\Inventario\Controllers\CatalogoController;
use App\Domains\Inventario\Controllers\DashboardController;
use App\Domains\Inventario\Controllers\DisponibilidadController;
use App\Domains\Inventario\Controllers\InventarioAuditoriaController;
use App\Domains\Inventario\Controllers\InventarioDespachoController;
use App\Domains\Inventario\Controllers\InventarioDevolucionController;
use App\Domains\Inventario\Controllers\InventarioEventoIntegracionController;
use App\Domains\Inventario\Controllers\InventarioPackingController;
use App\Domains\Inventario\Controllers\InventarioPickingController;
use App\Domains\Inventario\Controllers\KardexController;
use App\Domains\Inventario\Controllers\LoteController;
use App\Domains\Inventario\Controllers\MovimientoController;
use App\Domains\Inventario\Controllers\ProductoController;
use App\Domains\Inventario\Controllers\ReporteInventarioController;
use App\Domains\Inventario\Controllers\ReposicionController;
use App\Domains\Inventario\Controllers\ReservaController;
use App\Domains\Inventario\Controllers\StockUbicacionController;
use App\Domains\Inventario\Controllers\TomaFisicaController;
use App\Domains\Inventario\Controllers\UbicacionController;
use App\Domains\Inventario\Controllers\ValorizacionController;
use App\Domains\Rrhh\Controllers\ArcoController;
use App\Domains\Rrhh\Controllers\CentralizacionController;
use App\Domains\Rrhh\Controllers\ContratoController;
use App\Domains\Rrhh\Controllers\EmpleadoController;
use App\Domains\Rrhh\Controllers\EmrclController;
use App\Domains\Rrhh\Controllers\FiniquitoController;
use App\Domains\Rrhh\Controllers\LibroRemuneracionesController;
use App\Domains\Rrhh\Controllers\LiquidacionController;
use App\Domains\Rrhh\Controllers\LreController;
use App\Domains\Rrhh\Controllers\ParametroPrevisionalController;
use App\Domains\Rrhh\Controllers\PreviredController;
use App\Domains\Rrhh\Controllers\VacacionesController;
use App\Domains\Soporte\Controllers\SoporteController;
use App\Domains\Tesoreria\Controllers\BancoController;
use App\Domains\Tesoreria\Controllers\ConciliacionController;
use App\Domains\Tesoreria\Controllers\CuentaProveedorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RBAC granular por endpoint
|--------------------------------------------------------------------------
| Cada ruta sensible declara el/los permiso(s) requeridos via el middleware
| 'permiso:' (EnsureUserHasPermission), que pasa si el usuario tiene AL MENOS
| UNO de los permisos listados. Los roles con jerarquia >= 100 (Super Admin)
| reciben todos los permisos; jerarquia >= 80 (Administrador) recibe ademas
| el set completo de inventario (ver ModuloPermisos::permisosUsuario).
| Las rutas de perfil de empresa quedan solo autenticadas (sin permiso
| especifico) porque son configuracion propia de cada miembro de la empresa.
| El modulo Inventario aplica su propia autorizacion granular a nivel de
| controller (InventarioPermisoService), por eso su grupo no repite permiso:.
*/

// Health check operativo (publico, sin auth): el equipo verifica el estado de los
// servicios sin SSH. 200 = todo OK, 503 = algun componente caido.
// Throttle: 30 req/min por IP para evitar uso como oraculo o DDoS.
Route::get('/health', HealthController::class)->middleware('throttle:30,1');

Route::prefix('auth')->group(function () {
    // Rate-limiting contra fuerza bruta / credential stuffing: 6 intentos por minuto
    // por IP. Sin esto los endpoints publicos de credenciales son atacables sin limite.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/token-login', [AuthController::class, 'tokenLogin']);
    });

    Route::middleware(['auth:sanctum', 'track.ultimo.acceso'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Onboarding de empresa: accesible a un usuario autenticado que AUN no tiene
// empresa (recien provisionado). No se exige check.subscription porque es el
// paso previo a configurar la empresa/plan.
Route::middleware(['auth:sanctum', 'track.ultimo.acceso'])->group(function () {
    // Rate-limiting: aunque requieren sesion, son endpoints livianos que no deberian
    // llamarse mas de un puñado de veces por usuario legitimo (verificacion de RUT
    // repetida o reintentos de onboarding). Throttle acota abuso/scripting.
    Route::get('/empresas/verificar-rut', [EmpresaController::class, 'verificarRut'])->middleware('throttle:30,1');
    Route::post('/empresas/onboarding', [EmpresaController::class, 'onboarding'])->middleware('throttle:30,1');

    // Política de privacidad y consentimiento — Ley 21.719 Fase 4
    Route::prefix('privacidad')->group(function () {
        Route::get('/politica', [PrivacidadController::class, 'politicaActiva']);
        Route::get('/mi-consentimiento', [PrivacidadController::class, 'miConsentimiento']);
        Route::post('/consentimiento', [PrivacidadController::class, 'aceptar']);
        Route::delete('/consentimiento', [PrivacidadController::class, 'revocar']);
        Route::post('/politica', [PrivacidadController::class, 'crearPolitica'])->middleware('permiso:usuarios.gestionar');
    });

    // Cambio de empresa activa (multiempresa/multitenant).
    // Sin check.subscription: el cambio debe ser posible incluso con plan vencido
    // para que el usuario pueda navegar a otra empresa que sí esté activa.
    Route::prefix('empresa')->group(function () {
        Route::get('mis-empresas', [EmpresaCambioController::class, 'misEmpresas']);
        Route::post('cambiar', [EmpresaCambioController::class, 'cambiar']);
    });
});

Route::middleware(['auth:sanctum', 'track.ultimo.acceso', 'check.subscription', 'subscription.writable'])->group(function () {
    // Gestion de usuarios y roles. RBAC en dos capas (H15):
    //  - Capa 1 (aqui): gate grueso de capacidad -> permiso:usuarios.ver|gestionar.
    //  - Capa 2 (controller): logica relativa por instancia (jerarquia del objetivo
    //    vs la del solicitante, no-self, anti-escalada de permisos). NO movible a
    //    middleware porque depende del recurso concreto. Ambas capas coexisten.
    Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('permiso:usuarios.ver');
    Route::get('/usuarios/roles', [UsuarioController::class, 'roles'])->middleware('permiso:usuarios.ver');
    Route::post('/usuarios/invitar', [UsuarioController::class, 'invitar'])->middleware('permiso:usuarios.gestionar');
    Route::put('/usuarios/{id}/rol', [UsuarioController::class, 'actualizarRol'])->middleware('permiso:usuarios.gestionar');
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'desvincular'])->middleware('permiso:usuarios.gestionar');
    Route::post('/usuarios/roles', [UsuarioController::class, 'storeRol'])->middleware('permiso:usuarios.gestionar');
    Route::put('/usuarios/roles/{id}', [UsuarioController::class, 'updateRol'])->middleware('permiso:usuarios.gestionar');

    // Empresa - Perfil: lectura libre (cualquier miembro); escritura restringida a
    // usuarios.gestionar (mismo permiso que gestión de roles) dado que no existe
    // una clave empresa.configurar en ModuloPermisos.
    Route::get('/empresas/perfil', [EmpresaController::class, 'perfil']);
    Route::put('/empresas/perfil', [EmpresaController::class, 'actualizarPerfil'])->middleware('permiso:usuarios.gestionar');
    Route::post('/empresas/logo', [EmpresaController::class, 'subirLogo'])->middleware('permiso:usuarios.gestionar');
    Route::get('/empresas/catalogo-bancos', [EmpresaController::class, 'catalogoBancos']);

    // Empresa - Cuentas Bancarias: escritura con tesoreria.crear (vector de fraude
    // si cualquier usuario de lectura pudiera añadir/editar cuentas bancarias propias).
    Route::post('/empresas/bancos', [EmpresaController::class, 'agregarBanco'])->middleware('permiso:tesoreria.crear');
    Route::put('/empresas/bancos/{id}', [EmpresaController::class, 'actualizarBanco'])->middleware('permiso:tesoreria.crear');
    Route::delete('/empresas/bancos/{id}', [EmpresaController::class, 'eliminarBanco'])->middleware('permiso:tesoreria.crear');

    // Empresa - Centros de Costos: escritura con contabilidad.crear (los CC son
    // estructuras contables; no existe clave empresa.configurar en ModuloPermisos).
    Route::get('/empresas/centros-costo', [EmpresaController::class, 'listarCentros']);
    Route::get('/centros-costo', [EmpresaController::class, 'listarCentros']);
    Route::post('/empresas/centros-costo', [EmpresaController::class, 'agregarCentro'])->middleware('permiso:contabilidad.crear');
    Route::put('/empresas/centros-costo/{id}', [EmpresaController::class, 'actualizarCentro'])->middleware('permiso:contabilidad.crear');
    Route::delete('/empresas/centros-costo/{id}', [EmpresaController::class, 'eliminarCentro'])->middleware('permiso:contabilidad.crear');

    // Core
    Route::get('/paises', [PaisController::class, 'index']);

    // Dashboard principal — resumen de KPIs, serie ventas, top clientes y facturas urgentes.
    // Accesible a cualquier usuario autenticado con suscripción activa; el aislamiento
    // multitenant lo garantiza EmpresaScope sobre los modelos consultados.
    Route::get('/dashboard/resumen', [DashboardResumenController::class, 'resumen']);

    // DPO — Auditoria PII (Ley 21.719 — Fase 3).
    // Solo administradores (jerarquia >= 80, permiso usuarios.gestionar) pueden
    // consultar el log. Las filas ya estan filtradas por empresa del solicitante.
    Route::get('/auditoria', [AuditoriaController::class, 'index'])->middleware('permiso:usuarios.gestionar');

    // Motor de alertas de cumplimiento y gestion (bandeja multitipo: periodos, F29, CxC/CxP, RRHH).
    Route::prefix('alertas')->group(function () {
        Route::get('/', [AlertaGenericaController::class, 'index'])->middleware('permiso:alertas.ver');
        Route::patch('/{id}', [AlertaGenericaController::class, 'resolver'])->middleware(['permiso:alertas.gestionar', 'subscription.writable']);
    });

    // Registro de incidentes de seguridad (Fase 6 — Ley 21.663 / 21.719).
    // Solo administradores (permiso usuarios.gestionar). Aislamiento multitenant
    // garantizado por EmpresaScope sobre IncidenteSeguridad.
    Route::get('/incidentes', [IncidenteSeguridadController::class, 'index'])->middleware('permiso:usuarios.gestionar');
    Route::post('/incidentes', [IncidenteSeguridadController::class, 'store'])->middleware('permiso:usuarios.gestionar');
    Route::put('/incidentes/{id}', [IncidenteSeguridadController::class, 'update'])->middleware('permiso:usuarios.gestionar');

    // ---------------------------------------------------------------------
    // Comercial - Clientes
    // ---------------------------------------------------------------------
    Route::get('/clientes', [ClienteController::class, 'index'])->middleware('permiso:clientes.ver,ventas.ver');
    Route::post('/clientes', [ClienteController::class, 'store'])->middleware('permiso:clientes.crear');
    Route::get('/clientes/ficha/{id}', [ClienteController::class, 'ficha'])->middleware('permiso:clientes.ver,ventas.ver');
    Route::get('/clientes/{id}', [ClienteController::class, 'show'])->middleware('permiso:clientes.ver,ventas.ver');
    Route::put('/clientes/{id}', [ClienteController::class, 'update'])->middleware('permiso:clientes.crear');
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])->middleware('permiso:clientes.crear');
    Route::put('/clientes/{id}/activar', [ClienteController::class, 'activar'])->middleware('permiso:clientes.crear');
    Route::patch('/clientes/{id}/reactivar', [ClienteController::class, 'reactivar'])->middleware('permiso:clientes.crear');
    Route::post('/clientes/{id}/cruzar-documentos', [ClienteController::class, 'cruzarDocumentos'])->middleware('permiso:clientes.crear');

    // Endpoints dedicados de anticipos de clientes (con saldo disponible) -- mirror de anticipos-proveedores
    Route::get('/anticipos-clientes', [AnticipoClienteController::class, 'index'])->middleware('permiso:clientes.ver,ventas.ver');
    Route::post('/anticipos-clientes', [AnticipoClienteController::class, 'store'])->middleware('permiso:clientes.crear,ventas.crear');
    Route::post('/anticipos-clientes/{id}/aplicar', [AnticipoClienteController::class, 'aplicar'])->middleware('permiso:clientes.crear,ventas.crear');

    // ---------------------------------------------------------------------
    // Comercial - Proveedores
    // ---------------------------------------------------------------------
    Route::get('/proveedores/catalogo', [ProveedorController::class, 'catalogo'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::get('/proveedores/ficha/{id}', [ProveedorController::class, 'ficha'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::post('/proveedores/anticipos', [ProveedorController::class, 'guardarAnticipo'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::post('/proveedores/anticipos/{id}/pdf', [ProveedorController::class, 'subirPdfAnticipo'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::get('/proveedores/anticipos/{id}/pdf', [ProveedorController::class, 'descargarPdfAnticipo'])->middleware('permiso:proveedores.ver,compras.ver');

    // Endpoints dedicados de anticipos a proveedores (con saldo disponible)
    Route::get('/anticipos-proveedores', [AnticipoProveedorController::class, 'index'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::post('/anticipos-proveedores', [AnticipoProveedorController::class, 'store'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::post('/anticipos-proveedores/{id}/aplicar', [AnticipoProveedorController::class, 'aplicar'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::post('/proveedores/{id}/cruzar-documentos', [ProveedorController::class, 'cruzarDocumentos'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::delete('/proveedores/{id}', [ProveedorController::class, 'destroy'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::put('/proveedores/{id}/activar', [ProveedorController::class, 'activar'])->middleware('permiso:proveedores.crear,compras.crear');

    // Resource de proveedores (index/store/update/show; destroy en ruta dedicada arriba)
    Route::get('/proveedores', [ProveedorController::class, 'index'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::get('/proveedores/{id}', [ProveedorController::class, 'show'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::put('/proveedores/{id}', [ProveedorController::class, 'update'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::patch('/proveedores/{id}', [ProveedorController::class, 'update'])->middleware('permiso:proveedores.crear,compras.crear');

    // ---------------------------------------------------------------------
    // Comercial - Facturas
    // ---------------------------------------------------------------------
    Route::get('/facturas/historial', [FacturaController::class, 'historial'])->middleware('permiso:compras.ver');
    Route::get('/facturas/check', [FacturaController::class, 'check'])->middleware('permiso:compras.ver');
    Route::get('/facturas/vencidas', [FacturaController::class, 'vencidas'])->middleware('permiso:compras.ver');
    // Throttle: exportacion masiva es operacion pesada (genera Excel completo), se
    // acota a 10 req/min para evitar abuso/uso como vector de denegacion de servicio.
    Route::get('/facturas/exportar/excel', [FacturaController::class, 'exportarExcel'])->middleware(['permiso:compras.ver', 'throttle:10,1']);
    Route::get('/facturas/disponibles-proyectos', [FacturaController::class, 'disponiblesProyectos'])->middleware('permiso:compras.ver,activos.ver');
    Route::get('/facturas/f50', [FacturaController::class, 'f50'])->middleware('permiso:compras.ver');

    // Resource de facturas (index/store/show; update y destroy no existen)
    Route::get('/facturas', [FacturaController::class, 'index'])->middleware('permiso:compras.ver');
    Route::post('/facturas', [FacturaController::class, 'store'])->middleware('permiso:compras.crear');
    Route::get('/facturas/{id}', [FacturaController::class, 'show'])->middleware('permiso:compras.ver');

    Route::get('/facturas/{id}/asiento', [FacturaController::class, 'verAsiento'])->middleware('permiso:compras.ver,contabilidad.ver');
    Route::post('/facturas/{id}/reclasificar', [FacturaController::class, 'reclasificarAsiento'])->middleware('permiso:contabilidad.crear');
    Route::get('/facturas/{id}/auditoria', [FacturaController::class, 'auditoria'])->middleware('permiso:compras.ver,contabilidad.ver');
    Route::post('/facturas/{id}/pagar', [FacturaController::class, 'pagar'])->middleware('permiso:tesoreria.crear,compras.crear');
    Route::post('/facturas/{id}/anular', [FacturaController::class, 'anular'])->middleware('permiso:compras.crear');
    Route::post('/facturas/{id}/nota-credito', [FacturaController::class, 'notaCredito'])->middleware('permiso:ventas.crear,compras.crear');
    Route::post('/facturas/{id}/nota-debito', [FacturaController::class, 'notaDebito'])->middleware('permiso:ventas.crear,compras.crear');
    Route::post('/facturas/{id}/vincular-proyecto', [FacturaController::class, 'vincularProyecto'])->middleware('permiso:activos.crear,compras.crear');
    Route::post('/facturas/{id}/pdf', [FacturaController::class, 'subirPdf'])->middleware('permiso:compras.crear,ventas.crear');
    Route::get('/facturas/{id}/pdf', [FacturaController::class, 'descargarPdf'])->middleware('permiso:compras.ver,ventas.ver');
    Route::get('/facturas/{id}/comprobante', [FacturaController::class, 'generarComprobantePdf'])->middleware('permiso:ventas.ver');

    // Documentos adjuntos (varios por factura: guías de despacho, comprobantes, etc.)
    Route::get('/facturas/{facturaId}/adjuntos', [DocumentoAdjuntoController::class, 'index'])->middleware('permiso:compras.ver,ventas.ver');
    Route::post('/facturas/{facturaId}/adjuntos', [DocumentoAdjuntoController::class, 'store'])->middleware('permiso:compras.crear,ventas.crear');
    Route::get('/facturas/{facturaId}/adjuntos/{adjuntoId}', [DocumentoAdjuntoController::class, 'show'])->middleware('permiso:compras.ver,ventas.ver');
    Route::delete('/facturas/{facturaId}/adjuntos/{adjuntoId}', [DocumentoAdjuntoController::class, 'destroy'])->middleware('permiso:compras.crear,ventas.crear');

    // Documentos adjuntos de Cotizacion y Orden de Compra (mismo mecanismo que Factura)
    Route::get('/cotizaciones/{cotizacionId}/adjuntos', [DocumentoAdjuntoController::class, 'indexCotizacion'])->middleware('permiso:ventas.ver');
    Route::post('/cotizaciones/{cotizacionId}/adjuntos', [DocumentoAdjuntoController::class, 'storeCotizacion'])->middleware('permiso:ventas.crear');
    Route::get('/cotizaciones/{cotizacionId}/adjuntos/{adjuntoId}', [DocumentoAdjuntoController::class, 'showCotizacion'])->middleware('permiso:ventas.ver');
    Route::delete('/cotizaciones/{cotizacionId}/adjuntos/{adjuntoId}', [DocumentoAdjuntoController::class, 'destroyCotizacion'])->middleware('permiso:ventas.crear');
    Route::get('/comercial/ordenes-compra/{ordenCompraId}/adjuntos', [DocumentoAdjuntoController::class, 'indexOrdenCompra'])->middleware('permiso:compras.ver');
    Route::post('/comercial/ordenes-compra/{ordenCompraId}/adjuntos', [DocumentoAdjuntoController::class, 'storeOrdenCompra'])->middleware('permiso:compras.crear');
    Route::get('/comercial/ordenes-compra/{ordenCompraId}/adjuntos/{adjuntoId}', [DocumentoAdjuntoController::class, 'showOrdenCompra'])->middleware('permiso:compras.ver');
    Route::delete('/comercial/ordenes-compra/{ordenCompraId}/adjuntos/{adjuntoId}', [DocumentoAdjuntoController::class, 'destroyOrdenCompra'])->middleware('permiso:compras.crear');

    // ---------------------------------------------------------------------
    // Comercial - Cotizaciones
    // ---------------------------------------------------------------------
    Route::get('/cotizaciones/pdf/{id}', [CotizacionController::class, 'generarPdf'])->middleware('permiso:ventas.ver');
    Route::put('/cotizaciones/{id}/estado', [CotizacionController::class, 'actualizarEstado'])->middleware('permiso:ventas.crear');
    Route::patch('/cotizaciones/{id}/estado', [CotizacionController::class, 'actualizarEstado'])->middleware('permiso:ventas.crear');
    Route::post('/cotizaciones/{id}/facturar', [CotizacionController::class, 'facturar'])->middleware('permiso:ventas.crear');
    Route::post('/cotizaciones/{id}/enviar', [CotizacionController::class, 'enviar'])->middleware('permiso:ventas.crear');

    // Resource de cotizaciones (index/store; show, update y destroy excluidos)
    Route::get('/cotizaciones', [CotizacionController::class, 'index'])->middleware('permiso:ventas.ver');
    Route::post('/cotizaciones', [CotizacionController::class, 'store'])->middleware('permiso:ventas.crear');
    Route::put('/cotizaciones/{id}', [CotizacionController::class, 'update'])->middleware('permiso:ventas.crear');

    // Comercial - Órdenes de Compra
    Route::get('/comercial/ordenes-compra', [OrdenCompraController::class, 'index'])->middleware('permiso:compras.ver');
    Route::post('/comercial/ordenes-compra', [OrdenCompraController::class, 'store'])->middleware('permiso:compras.crear');
    Route::get('/comercial/ordenes-compra/{ordenCompra}', [OrdenCompraController::class, 'show'])->middleware('permiso:compras.ver');
    Route::put('/comercial/ordenes-compra/{ordenCompra}', [OrdenCompraController::class, 'update'])->middleware('permiso:compras.crear');
    Route::delete('/comercial/ordenes-compra/{ordenCompra}', [OrdenCompraController::class, 'destroy'])->middleware('permiso:compras.crear');
    Route::post('/comercial/ordenes-compra/{ordenCompra}/recibir', [OrdenCompraController::class, 'recibirMercaderia'])->middleware('permiso:compras.crear');

    // ---------------------------------------------------------------------
    // Tesoreria - Cuentas de Proveedores
    // ---------------------------------------------------------------------
    Route::get('/cuentas-bancarias/proveedor/{proveedorId}', [CuentaProveedorController::class, 'index'])->middleware('permiso:tesoreria.ver,proveedores.ver');
    Route::post('/cuentas-bancarias', [CuentaProveedorController::class, 'store'])->middleware('permiso:tesoreria.crear,proveedores.crear');
    Route::delete('/cuentas-bancarias/{id}', [CuentaProveedorController::class, 'destroy'])->middleware('permiso:tesoreria.crear,proveedores.crear');

    // ---------------------------------------------------------------------
    // Tesoreria - Bancos Propios y Conciliacion
    // ---------------------------------------------------------------------
    Route::get('/tesoreria/bancos-catalogo', [BancoController::class, 'catalogo'])->middleware('permiso:tesoreria.ver');
    Route::get('/tesoreria/cuentas-propias', [BancoController::class, 'cuentasEmpresa'])->middleware('permiso:tesoreria.ver');
    Route::post('/tesoreria/cuentas-propias', [BancoController::class, 'storeCuenta'])->middleware('permiso:tesoreria.crear');
    Route::post('/tesoreria/conciliar/factura-compra', [ConciliacionController::class, 'pagarFacturaCompra'])->middleware('permiso:tesoreria.crear');

    // Tesoreria - Bancos y Conciliacion
    Route::post('/banco/nomina/pagar', [BancoController::class, 'pagarNomina'])->middleware('permiso:tesoreria.crear');
    Route::get('/banco/cuentas', [BancoController::class, 'cuentasEmpresa'])->middleware('permiso:tesoreria.ver');
    Route::get('/banco/cuentas-imputables', [PlanCuentaController::class, 'imputables'])->middleware('permiso:tesoreria.ver,contabilidad.ver');
    Route::post('/banco/ingreso-manual', [BancoController::class, 'ingresoManual'])->middleware('permiso:tesoreria.crear');
    Route::post('/banco/importar', [BancoController::class, 'importarCartola'])->middleware('permiso:tesoreria.crear');

    // Tesoreria - Movimientos
    Route::get('/banco/movimientos/pendientes/{idCuenta}', [ConciliacionController::class, 'movimientosPendientes'])->middleware('permiso:tesoreria.ver');
    Route::get('/banco/movimientos/{id}/sugerencias', [ConciliacionController::class, 'sugerencias'])->middleware('permiso:tesoreria.ver');
    Route::get('/banco/movimientos/{idCuenta}', [BancoController::class, 'movimientos'])->middleware('permiso:tesoreria.ver');

    // Tesoreria - Mesa de Conciliacion
    Route::get('/banco/anticipos-pendientes', [ConciliacionController::class, 'anticiposPendientes'])->middleware('permiso:tesoreria.ver');
    Route::post('/banco/movimientos/{id}/descartar', [ConciliacionController::class, 'descartar'])->middleware('permiso:tesoreria.crear');
    Route::post('/banco/movimientos/conciliar', [ConciliacionController::class, 'conciliar'])->middleware('permiso:tesoreria.crear');
    Route::post('/banco/movimientos/conciliar-anticipo', [ConciliacionController::class, 'conciliarAnticipo'])->middleware('permiso:tesoreria.crear');
    Route::post('/banco/movimientos/conciliar-facturas', [ConciliacionController::class, 'conciliarFacturas'])->middleware('permiso:tesoreria.crear');

    // ---------------------------------------------------------------------
    // Contabilidad - Plan de Cuentas
    // ---------------------------------------------------------------------
    Route::get('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'index'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'store'])->middleware('permiso:contabilidad.crear');
    Route::put('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'update'])->middleware('permiso:contabilidad.crear');
    Route::delete('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'destroy'])->middleware('permiso:contabilidad.crear');

    // Contabilidad - Asientos Contables
    Route::get('/contabilidad/asientos', [AsientoContableController::class, 'index'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/asientos', [AsientoContableController::class, 'store'])->middleware('permiso:contabilidad.crear');
    Route::get('/contabilidad/asientos/{id}', [AsientoContableController::class, 'show'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/asientos/{id}/reversar', [AsientoContableController::class, 'reversar'])->middleware('permiso:contabilidad.crear');
    Route::post('/contabilidad/asiento-manual/avanzado', [AsientoContableController::class, 'storeAvanzado'])->middleware('permiso:contabilidad.crear');

    // Contabilidad - Bloqueo de periodo (inmutabilidad). El cierre es manual; la
    // reapertura exige jerarquia >= 80 (validada en el service) y queda auditada.
    Route::get('/contabilidad/periodos', [PeriodoContableController::class, 'index'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/periodos/cerrar', [PeriodoContableController::class, 'cerrar'])->middleware('permiso:contabilidad.cerrar_periodo');
    Route::post('/contabilidad/periodos/reabrir', [PeriodoContableController::class, 'reabrir'])->middleware('permiso:contabilidad.cerrar_periodo');

    // Contabilidad - Libros diarios y mayores
    Route::get('/contabilidad/libro-diario', [ReporteController::class, 'libroDiario'])->middleware('permiso:contabilidad.ver');
    Route::get('/contabilidad/reportes/libro-mayor', [ReporteController::class, 'libroMayor'])->middleware('permiso:contabilidad.ver');
    Route::get('/contabilidad/reportes/balance-comprobacion', [ReporteController::class, 'balanceComprobacion'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/reportes/exportar', [ReporteController::class, 'solicitarExportacion'])->middleware('permiso:contabilidad.ver');
    Route::get('/contabilidad/reportes/exportar/{id}', [ReporteController::class, 'estadoExportacion'])->middleware('permiso:contabilidad.ver');
    Route::get('/contabilidad/reportes/exportar', [ReporteController::class, 'historialExportaciones'])->middleware('permiso:contabilidad.ver');

    // Contabilidad - Formularios 29 y 22 (Renta)
    Route::get('/impuestos/cierre-f29/simular/{mes}/{anio}', [ImpuestosController::class, 'simularF29'])->middleware('permiso:tributario.ver');
    Route::get('/impuestos/cierre-f29/descargar/{mes}/{anio}', [ImpuestosController::class, 'descargarF29'])->middleware('permiso:tributario.ver');
    Route::post('/impuestos/cierre-f29/ejecutar', [ImpuestosController::class, 'ejecutarF29'])->middleware('permiso:tributario.crear');
    Route::get('/renta/pre-calculo/{anio}', [ImpuestosController::class, 'preCalculoRenta'])->middleware('permiso:tributario.ver');
    Route::get('/renta/mapeo', [ImpuestosController::class, 'obtenerMapeo'])->middleware('permiso:tributario.ver');
    Route::post('/renta/mapeo', [ImpuestosController::class, 'guardarMapeo'])->middleware('permiso:tributario.crear');
    Route::delete('/renta/mapeo/{id}', [ImpuestosController::class, 'eliminarMapeo'])->middleware('permiso:tributario.crear');

    // LCV — Libro de Compras y Ventas (Res. Ex. SII N°45/2003)
    Route::get('/impuestos/lcv/ventas/{mes}/{anio}', [LibroComprasVentasController::class, 'ventas'])->middleware('permiso:tributario.ver');
    Route::get('/impuestos/lcv/compras/{mes}/{anio}', [LibroComprasVentasController::class, 'compras'])->middleware('permiso:tributario.ver');
    Route::get('/impuestos/lcv/ventas/{mes}/{anio}/descargar', [LibroComprasVentasController::class, 'descargarVentas'])->middleware('permiso:tributario.ver');
    Route::get('/impuestos/lcv/compras/{mes}/{anio}/descargar', [LibroComprasVentasController::class, 'descargarCompras'])->middleware('permiso:tributario.ver');

    // Correccion Monetaria (parte de contabilidad)
    Route::prefix('correccion-monetaria')->group(function () {
        Route::get('/indices/{anio}', [CorreccionMonetariaController::class, 'indices'])->middleware('permiso:contabilidad.ver');
        Route::post('/indices', [CorreccionMonetariaController::class, 'guardarIndice'])->middleware('permiso:contabilidad.crear');
        Route::get('/configuracion', [CorreccionMonetariaController::class, 'configuracion'])->middleware('permiso:contabilidad.ver');
        Route::put('/configuracion', [CorreccionMonetariaController::class, 'actualizarConfiguracion'])->middleware('permiso:contabilidad.crear');
        Route::get('/cuentas', [CorreccionMonetariaController::class, 'cuentasConfiguracion'])->middleware('permiso:contabilidad.ver');
        Route::put('/cuentas', [CorreccionMonetariaController::class, 'actualizarCuentasConfiguracion'])->middleware('permiso:contabilidad.crear');
        Route::post('/cuentas', [CorreccionMonetariaController::class, 'agregarCuenta'])->middleware('permiso:contabilidad.crear');
        Route::get('/estado/{mes}/{anio}', [CorreccionMonetariaController::class, 'estadoPeriodo'])->middleware('permiso:contabilidad.ver');
        Route::get('/simular/{mes}/{anio}', [CorreccionMonetariaController::class, 'simular'])->middleware('permiso:contabilidad.ver');
        Route::post('/ejecutar', [CorreccionMonetariaController::class, 'ejecutar'])->middleware('permiso:contabilidad.crear');
        Route::get('/historial', [CorreccionMonetariaController::class, 'historial'])->middleware('permiso:contabilidad.ver');
    });

    // Contabilidad - Anulaciones
    Route::post('/anulacion/buscar', [AnulacionController::class, 'buscar'])->middleware('permiso:compras.ver,ventas.ver,contabilidad.ver');
    Route::post('/anulacion/anular', [AnulacionController::class, 'anular'])->middleware('permiso:compras.crear,ventas.crear,contabilidad.crear');

    // Contabilidad - Aging (Cuentas por Cobrar y por Pagar por Antigüedad)
    Route::get('/contabilidad/ar-aging', [ArAgingController::class, 'index'])->middleware('permiso:contabilidad.ver');
    Route::get('/contabilidad/ap-aging', [ApAgingController::class, 'index'])->middleware('permiso:contabilidad.ver');

    // ---------------------------------------------------------------------
    // Activos Fijos
    // ---------------------------------------------------------------------
    Route::get('/activos', [ActivoFijoController::class, 'index'])->middleware('permiso:activos.ver');
    Route::get('/activos/pendientes', [ActivoFijoController::class, 'pendientes'])->middleware('permiso:activos.ver');
    Route::post('/activos', [ActivoFijoController::class, 'store'])->middleware('permiso:activos.crear');
    Route::get('/activos/parametros', [ActivoFijoController::class, 'parametros'])->middleware('permiso:activos.ver');
    Route::post('/activos/depreciar-mes', [ActivoFijoController::class, 'depreciarMes'])->middleware('permiso:activos.crear');
    Route::get('/activos/{id}/amortizacion', [ActivoFijoController::class, 'amortizacion'])->middleware('permiso:activos.ver');
    Route::put('/activos/{id}/baja', [ActivoFijoController::class, 'darDeBaja'])->middleware('permiso:activos.crear');
    Route::put('/activos/{id}', [ActivoFijoController::class, 'update'])->middleware('permiso:activos.crear');

    // Activos Fijos - Proyectos
    Route::get('/activos/proyectos/facturas-disponibles', [ActivoFijoController::class, 'facturasDisponibles'])->middleware('permiso:activos.ver');
    Route::post('/activos/proyectos/{id}/facturas', [ActivoFijoController::class, 'imputarFactura'])->middleware('permiso:activos.crear');
    Route::put('/activos/proyectos/{id}/activar', [ActivoFijoController::class, 'activarProyecto'])->middleware('permiso:activos.crear');
    Route::put('/activos/proyectos/{id}', [ActivoFijoController::class, 'updateProyecto'])->middleware('permiso:activos.crear');
    Route::delete('/activos/proyectos/{id}', [ActivoFijoController::class, 'deleteProyecto'])->middleware('permiso:activos.crear');
    Route::delete('/activos/proyectos/{proyectoId}/facturas/{facturaId}', [ActivoFijoController::class, 'desvincularFactura'])->middleware('permiso:activos.crear');
    Route::get('/activos/proyectos', [ActivoFijoController::class, 'proyectos'])->middleware('permiso:activos.ver');
    Route::post('/activos/proyectos', [ActivoFijoController::class, 'storeProyecto'])->middleware('permiso:activos.crear');
    Route::get('/activos/proyectos/{id}/analisis', [ActivoFijoController::class, 'analisisProyecto'])->middleware('permiso:activos.ver');

    // ---------------------------------------------------------------------
    // Inventario, Bodegas y Movimientos
    // (autorizacion granular aplicada en los controllers via InventarioPermisoService)
    // ---------------------------------------------------------------------
    Route::prefix('inventario')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dashboard']);

        Route::get('/auditoria', [InventarioAuditoriaController::class, 'index']);
        Route::get('/auditoria/resumen', [InventarioAuditoriaController::class, 'resumen']);
        Route::get('/auditoria/{id}', [InventarioAuditoriaController::class, 'show']);

        Route::get('/eventos-integracion', [InventarioEventoIntegracionController::class, 'index']);
        Route::get('/eventos-integracion/resumen', [InventarioEventoIntegracionController::class, 'resumen']);
        Route::get('/eventos-integracion/{id}', [InventarioEventoIntegracionController::class, 'show']);
        Route::post('/eventos-integracion/{id}/procesar', [InventarioEventoIntegracionController::class, 'procesar']);
        Route::post('/eventos-integracion/{id}/ignorar', [InventarioEventoIntegracionController::class, 'ignorar']);
        Route::post('/eventos-integracion/{id}/error', [InventarioEventoIntegracionController::class, 'error']);

        Route::get('/reportes/stock', [ReporteInventarioController::class, 'reporteStock']);
        Route::get('/reportes/movimientos', [ReporteInventarioController::class, 'reporteMovimientos']);
        Route::get('/reportes/valorizacion', [ReporteInventarioController::class, 'reporteValorizacion']);
        Route::get('/reportes/lotes', [ReporteInventarioController::class, 'reporteLotes']);
        Route::get('/reportes/reservas', [ReporteInventarioController::class, 'reporteReservas']);
        Route::get('/reportes/tomas-fisicas', [ReporteInventarioController::class, 'reporteTomasFisicas']);
        Route::get('/reportes/ajustes', [ReporteInventarioController::class, 'reporteAjustes']);
        Route::get('/reportes/reposicion-alertas', [ReporteInventarioController::class, 'reporteReposicionAlertas']);
        Route::get('/reportes/picking', [InventarioPickingController::class, 'reporte']);
        Route::get('/reportes/packing', [InventarioPackingController::class, 'reporte']);
        Route::get('/reportes/despachos', [InventarioDespachoController::class, 'reporte']);
        Route::get('/reportes/devoluciones', [InventarioDevolucionController::class, 'reporte']);
        // Throttle: exportacion masiva de reportes de inventario es pesada; se acota
        // a 10 req/min para evitar abuso/uso como vector de denegacion de servicio.
        Route::get('/reportes/{tipo}/exportar-csv', [ReporteInventarioController::class, 'exportarReporteCsv'])->middleware('throttle:10,1');

        Route::get('/catalogos', [CatalogoController::class, 'catalogos']);

        Route::get('/ubicaciones', [UbicacionController::class, 'ubicaciones']);
        Route::post('/ubicaciones', [UbicacionController::class, 'storeUbicacion']);
        Route::get('/ubicaciones/{id}/stock', [UbicacionController::class, 'stockUbicacion']);
        Route::get('/ubicaciones/{id}', [UbicacionController::class, 'showUbicacion']);
        Route::put('/ubicaciones/{id}', [UbicacionController::class, 'updateUbicacion']);
        Route::get('/stock-ubicaciones', [StockUbicacionController::class, 'stockUbicaciones']);
        Route::post('/stock-ubicaciones/mover', [StockUbicacionController::class, 'moverStockUbicacion']);
        Route::post('/putaway/confirmar', [StockUbicacionController::class, 'confirmarPutaway']);

        Route::get('/picking', [InventarioPickingController::class, 'index']);
        Route::post('/picking', [InventarioPickingController::class, 'store']);
        Route::get('/picking/{id}', [InventarioPickingController::class, 'show']);
        Route::post('/picking/{id}/asignar', [InventarioPickingController::class, 'asignar']);
        Route::post('/picking/{id}/iniciar', [InventarioPickingController::class, 'iniciar']);
        Route::post('/picking/{id}/confirmar', [InventarioPickingController::class, 'confirmar']);
        Route::post('/picking/{id}/cancelar', [InventarioPickingController::class, 'cancelar']);

        Route::get('/packing', [InventarioPackingController::class, 'index']);
        Route::post('/packing', [InventarioPackingController::class, 'store']);
        Route::get('/packing/{id}', [InventarioPackingController::class, 'show']);
        Route::post('/packing/{id}/iniciar', [InventarioPackingController::class, 'iniciar']);
        Route::post('/packing/{id}/confirmar', [InventarioPackingController::class, 'confirmar']);
        Route::post('/packing/{id}/cancelar', [InventarioPackingController::class, 'cancelar']);

        Route::get('/despachos', [InventarioDespachoController::class, 'index']);
        Route::post('/despachos', [InventarioDespachoController::class, 'store']);
        Route::get('/despachos/{id}/reversable', [InventarioDevolucionController::class, 'reversable']);
        Route::get('/despachos/{id}', [InventarioDespachoController::class, 'show']);
        Route::post('/despachos/{id}/iniciar', [InventarioDespachoController::class, 'iniciar']);
        Route::post('/despachos/{id}/confirmar', [InventarioDespachoController::class, 'confirmar']);
        Route::post('/despachos/{id}/cancelar', [InventarioDespachoController::class, 'cancelar']);

        Route::get('/devoluciones', [InventarioDevolucionController::class, 'index']);
        Route::post('/devoluciones', [InventarioDevolucionController::class, 'store']);
        Route::get('/devoluciones/{id}', [InventarioDevolucionController::class, 'show']);
        Route::post('/devoluciones/{id}/confirmar', [InventarioDevolucionController::class, 'confirmar']);
        Route::post('/devoluciones/{id}/cancelar', [InventarioDevolucionController::class, 'cancelar']);

        Route::get('/productos', [ProductoController::class, 'index']);
        Route::post('/productos', [ProductoController::class, 'store']);

        Route::get('/bodegas', [BodegaController::class, 'bodegas']);
        Route::post('/bodegas', [BodegaController::class, 'storeBodega']);

        Route::get('/movimientos', [MovimientoController::class, 'movimientos']);
        Route::post('/movimientos', [MovimientoController::class, 'registrarMovimiento']);

        Route::get('/kardex', [KardexController::class, 'kardex']);
        Route::get('/productos/{id}/kardex', [KardexController::class, 'kardexProducto']);

        Route::get('/valorizacion', [ValorizacionController::class, 'valorizacion']);
        Route::get('/productos/{id}/valorizacion', [ValorizacionController::class, 'valorizacionProducto']);

        Route::get('/ajustes-criticos/tipos', [AjusteCriticoController::class, 'tiposAjusteCritico']);
        Route::get('/ajustes-criticos', [AjusteCriticoController::class, 'ajustesCriticos']);
        Route::post('/ajustes-criticos', [AjusteCriticoController::class, 'registrarAjusteCritico']);
        Route::get('/ajustes-criticos/{id}', [AjusteCriticoController::class, 'verAjusteCritico']);
        Route::post('/ajustes-criticos/{id}/anular', [AjusteCriticoController::class, 'anularAjusteCritico']);

        Route::get('/lotes', [LoteController::class, 'lotes']);
        Route::post('/lotes', [LoteController::class, 'storeLote']);
        Route::get('/lotes/{id}/stock', [LoteController::class, 'stockLote']);
        Route::get('/lotes/{id}', [LoteController::class, 'showLote']);
        Route::put('/lotes/{id}', [LoteController::class, 'updateLote']);

        Route::get('/productos/{id}/lotes', [LoteController::class, 'lotesProducto']);

        Route::get('/disponibilidad', [DisponibilidadController::class, 'disponibilidad']);
        Route::get('/productos/{id}/disponibilidad', [DisponibilidadController::class, 'disponibilidadProducto']);

        Route::get('/reglas-reposicion', [ReposicionController::class, 'reglasReposicion']);
        Route::post('/reglas-reposicion', [ReposicionController::class, 'storeReglaReposicion']);
        Route::get('/reglas-reposicion/{id}', [ReposicionController::class, 'showReglaReposicion']);
        Route::put('/reglas-reposicion/{id}', [ReposicionController::class, 'updateReglaReposicion']);
        Route::delete('/reglas-reposicion/{id}', [ReposicionController::class, 'destroyReglaReposicion']);
        Route::get('/alertas', [AlertaController::class, 'alertas']);
        Route::get('/reposicion/sugerencias', [ReposicionController::class, 'sugerenciasReposicion']);

        Route::get('/reservas', [ReservaController::class, 'reservas']);
        Route::post('/reservas', [ReservaController::class, 'storeReserva']);
        Route::get('/reservas/{id}', [ReservaController::class, 'showReserva']);
        Route::post('/reservas/{id}/cancelar', [ReservaController::class, 'cancelarReserva']);
        Route::post('/reservas/{id}/liberar', [ReservaController::class, 'liberarReserva']);
        Route::post('/reservas/{id}/consumir', [ReservaController::class, 'consumirReserva']);

        Route::get('/productos/{id}', [ProductoController::class, 'show']);
        Route::put('/productos/{id}', [ProductoController::class, 'update']);

        Route::get('/tomas-fisicas', [TomaFisicaController::class, 'tomasFisicas']);
        Route::post('/tomas-fisicas', [TomaFisicaController::class, 'storeTomaFisica']);
        Route::get('/tomas-fisicas/{id}', [TomaFisicaController::class, 'showTomaFisica']);
        Route::post('/tomas-fisicas/{id}/iniciar', [TomaFisicaController::class, 'iniciarTomaFisica']);
        Route::post('/tomas-fisicas/{id}/conteos', [TomaFisicaController::class, 'registrarConteosTomaFisica']);
        Route::post('/tomas-fisicas/{id}/cerrar', [TomaFisicaController::class, 'cerrarTomaFisica']);
        Route::post('/tomas-fisicas/{id}/ajustar', [TomaFisicaController::class, 'ajustarTomaFisica']);
        Route::post('/tomas-fisicas/{id}/cancelar', [TomaFisicaController::class, 'cancelarTomaFisica']);
    });

    // ---------------------------------------------------------------------
    // RRHH y Remuneraciones (Chile)
    // ---------------------------------------------------------------------
    Route::prefix('rrhh')->group(function () {
        // Personal (R1)
        Route::get('/empleados', [EmpleadoController::class, 'index'])->middleware('permiso:rrhh.empleados.ver');
        Route::post('/empleados', [EmpleadoController::class, 'store'])->middleware('permiso:rrhh.empleados.crear');
        Route::get('/empleados/{id}', [EmpleadoController::class, 'show'])->middleware('permiso:rrhh.empleados.ver');
        Route::put('/empleados/{id}', [EmpleadoController::class, 'update'])->middleware('permiso:rrhh.empleados.editar');
        Route::delete('/empleados/{id}', [EmpleadoController::class, 'destroy'])->middleware('permiso:rrhh.empleados.editar');

        // Derechos ARCO+ (Fase 5 — Ley 21.719)
        // Throttle: exportacion de datos personales (ARCO+) es sensible y pesada;
        // se acota a 10 req/min para evitar abuso/exfiltracion masiva.
        Route::get('/empleados/{id}/datos-personales', [ArcoController::class, 'exportar'])->middleware(['permiso:rrhh.empleados.ver', 'throttle:10,1']);
        Route::post('/empleados/{id}/bloquear', [ArcoController::class, 'bloquear'])->middleware('permiso:usuarios.gestionar');
        Route::post('/empleados/{id}/anonimizar', [ArcoController::class, 'suprimir'])->middleware('permiso:usuarios.gestionar');

        // Contratos (R1)
        Route::get('/empleados/{empleadoId}/contratos', [ContratoController::class, 'indexPorEmpleado'])->middleware('permiso:rrhh.empleados.ver');
        Route::post('/empleados/{empleadoId}/contratos', [ContratoController::class, 'store'])->middleware('permiso:rrhh.contratos.crear');
        Route::get('/contratos/{id}', [ContratoController::class, 'show'])->middleware('permiso:rrhh.empleados.ver');
        Route::post('/contratos/{id}/terminar', [ContratoController::class, 'terminar'])->middleware('permiso:rrhh.contratos.crear');
        Route::post('/contratos/{id}/haberes', [ContratoController::class, 'agregarHaber'])->middleware('permiso:rrhh.contratos.crear');

        // Liquidaciones de sueldo (R3)
        Route::get('/liquidaciones', [LiquidacionController::class, 'index'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::post('/liquidaciones/calcular', [LiquidacionController::class, 'calcular'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::get('/liquidaciones/{id}', [LiquidacionController::class, 'show'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::post('/liquidaciones/{id}/emitir', [LiquidacionController::class, 'emitir'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::post('/liquidaciones/{id}/anular', [LiquidacionController::class, 'anular'])->middleware('permiso:rrhh.remuneraciones.procesar');

        // Finiquitos (R4)
        Route::get('/finiquitos', [FiniquitoController::class, 'index'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::post('/finiquitos/calcular', [FiniquitoController::class, 'calcular'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::get('/finiquitos/{id}', [FiniquitoController::class, 'show'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::post('/finiquitos/{id}/firmar', [FiniquitoController::class, 'firmar'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::post('/finiquitos/{id}/anular', [FiniquitoController::class, 'anular'])->middleware('permiso:rrhh.remuneraciones.procesar');

        // Vacaciones: solicitud, saldo y aprobacion (Art. 67-70 Codigo del Trabajo)
        Route::get('/vacaciones/saldo/{empleadoId}', [VacacionesController::class, 'saldo'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::get('/vacaciones/solicitudes', [VacacionesController::class, 'index'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::post('/vacaciones/solicitudes', [VacacionesController::class, 'solicitar'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::post('/vacaciones/solicitudes/{id}/aprobar', [VacacionesController::class, 'aprobar'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::post('/vacaciones/solicitudes/{id}/rechazar', [VacacionesController::class, 'rechazar'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::post('/vacaciones/solicitudes/{id}/anular', [VacacionesController::class, 'anular'])->middleware('permiso:rrhh.remuneraciones.procesar');

        // Parametrización legal (R2): tasas, indicadores UF/UTM, tabla impuesto único
        Route::get('/parametros', [ParametroPrevisionalController::class, 'indexParametros'])->middleware('permiso:rrhh.parametros.ver');
        Route::post('/parametros', [ParametroPrevisionalController::class, 'storeParametro'])->middleware('permiso:rrhh.parametros.editar');
        Route::get('/indicadores', [ParametroPrevisionalController::class, 'indexIndicadores'])->middleware('permiso:rrhh.parametros.ver');
        Route::post('/indicadores', [ParametroPrevisionalController::class, 'storeIndicador'])->middleware('permiso:rrhh.parametros.editar');
        Route::get('/tabla-impuesto', [ParametroPrevisionalController::class, 'indexImpuesto'])->middleware('permiso:rrhh.parametros.ver');

        // R5 — Centralización contable de remuneraciones
        Route::get('/mapeo-contable', [CentralizacionController::class, 'indexMapeo'])->middleware('permiso:rrhh.parametros.ver');
        Route::post('/mapeo-contable', [CentralizacionController::class, 'upsertMapeo'])->middleware('permiso:rrhh.parametros.editar');
        Route::delete('/mapeo-contable/{tipo}', [CentralizacionController::class, 'destroyMapeo'])->middleware('permiso:rrhh.parametros.editar');
        Route::post('/centralizacion/{anio}/{mes}', [CentralizacionController::class, 'centralizar'])->middleware('permiso:rrhh.remuneraciones.procesar');

        // R6 — Previred: archivo previsional mensual
        Route::get('/previred/{anio}/{mes}/archivo', [PreviredController::class, 'archivo'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::get('/previred/{anio}/{mes}/preview', [PreviredController::class, 'preview'])->middleware('permiso:rrhh.remuneraciones.ver');

        // R7 — LRE: Libro de Remuneraciones Electrónico
        // El LRE se genera, valida y descarga desde aquí. La subida al portal Mi DT
        // es manual; el empleador registra el número de confirmación en confirmar-dt.
        Route::post('/lre/generar', [LreController::class, 'generar'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::post('/lre/{id}/validar', [LreController::class, 'validar'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::post('/lre/{id}/confirmar-dt', [LreController::class, 'confirmarDt'])->middleware('permiso:rrhh.remuneraciones.procesar');
        Route::get('/lre', [LreController::class, 'index'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::get('/lre/{id}/descargar', [LreController::class, 'descargar'])->middleware('permiso:rrhh.remuneraciones.ver');

        // R8 — EMRCL: Encuesta Mensual INE de Remuneraciones y Costos Laborales
        Route::get('/emrcl/{anio}/{mes}', [EmrclController::class, 'generar'])->middleware('permiso:rrhh.remuneraciones.ver');

        // R9 — Libro de Remuneraciones Digital (DFL-1 Art. 62 C.T.)
        Route::get('/libro-remuneraciones/{anio}/{mes}', [LibroRemuneracionesController::class, 'simular'])->middleware('permiso:rrhh.remuneraciones.ver');
        Route::get('/libro-remuneraciones/{anio}/{mes}/descargar', [LibroRemuneracionesController::class, 'descargar'])->middleware('permiso:rrhh.remuneraciones.ver');
    });
});

// DJ 1887 - Declaración Jurada de Rentas (SII)
Route::prefix('dj/1887')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [Dj1887Controller::class, 'index'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/generar', [Dj1887Controller::class, 'generar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::post('/{djEnvio}/validar', [Dj1887Controller::class, 'validar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::get('/{djEnvio}/descargar', [Dj1887Controller::class, 'descargar'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/{djEnvio}/confirmar-presentacion', [Dj1887Controller::class, 'confirmarPresentacion'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
});

// DJ 1879 - Retenciones de honorarios (SII)
Route::prefix('dj/1879')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [Dj1879Controller::class, 'index'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/generar', [Dj1879Controller::class, 'generar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::post('/{djEnvio}/validar', [Dj1879Controller::class, 'validar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::get('/{djEnvio}/descargar', [Dj1879Controller::class, 'descargar'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/{djEnvio}/confirmar-presentacion', [Dj1879Controller::class, 'confirmarPresentacion'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
});

// DJ 1947 — Propyme Transparente 14D N°8
Route::prefix('dj/1947')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [Dj1947Controller::class, 'index'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/generar', [Dj1947Controller::class, 'generar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::post('/{djEnvio}/validar', [Dj1947Controller::class, 'validar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::get('/{djEnvio}/descargar', [Dj1947Controller::class, 'descargar'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/{djEnvio}/confirmar-presentacion', [Dj1947Controller::class, 'confirmarPresentacion'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
});

// DJ 1835 — Retenciones Art. 59 LIR (Exterior)
Route::prefix('dj/1835')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [Dj1835Controller::class, 'index'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/generar', [Dj1835Controller::class, 'generar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::post('/{djEnvio}/validar', [Dj1835Controller::class, 'validar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::get('/{djEnvio}/descargar', [Dj1835Controller::class, 'descargar'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/{djEnvio}/confirmar-presentacion', [Dj1835Controller::class, 'confirmarPresentacion'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
});

// DJ 1837 — Retenciones y otros impuestos de trabajadores dependientes
Route::prefix('dj/1837')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [Dj1837Controller::class, 'index'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/generar', [Dj1837Controller::class, 'generar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::post('/{djEnvio}/validar', [Dj1837Controller::class, 'validar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::get('/{djEnvio}/descargar', [Dj1837Controller::class, 'descargar'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/{djEnvio}/confirmar-presentacion', [Dj1837Controller::class, 'confirmarPresentacion'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
});

// DJ 1926 — Impuesto adicional servicios digitales
Route::prefix('dj/1926')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [Dj1926Controller::class, 'index'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/generar', [Dj1926Controller::class, 'generar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::post('/{djEnvio}/validar', [Dj1926Controller::class, 'validar'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
    Route::get('/{djEnvio}/descargar', [Dj1926Controller::class, 'descargar'])->middleware('permiso:contabilidad.dj.ver');
    Route::post('/{djEnvio}/confirmar-presentacion', [Dj1926Controller::class, 'confirmarPresentacion'])->middleware(['subscription.writable', 'permiso:contabilidad.dj.procesar']);
});

// Propietarios de la empresa (para DJ 1947 / Propyme)
Route::prefix('empresa/propietarios')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [PropietariosController::class, 'index'])->middleware('permiso:contabilidad.ver');
    Route::post('/', [PropietariosController::class, 'store'])->middleware(['subscription.writable', 'permiso:contabilidad.crear']);
    Route::put('/{propietario}', [PropietariosController::class, 'update'])->middleware(['subscription.writable', 'permiso:contabilidad.crear']);
    Route::delete('/{propietario}', [PropietariosController::class, 'destroy'])->middleware(['subscription.writable', 'permiso:contabilidad.crear']);
});

// Honorarios recibidos
Route::prefix('honorarios')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [HonorariosController::class, 'index'])->middleware('permiso:compras.ver');
    Route::post('/', [HonorariosController::class, 'store'])->middleware(['permiso:compras.crear', 'subscription.writable']);
    Route::delete('/{honorariosRecibido}', [HonorariosController::class, 'destroy'])->middleware(['permiso:compras.crear', 'subscription.writable']);
});

// Soporte — proxy hacia api.tenri.cl
Route::prefix('soporte/tickets')->middleware(['auth:sanctum', 'check.subscription'])->group(function () {
    Route::get('/', [SoporteController::class, 'index'])->middleware('permiso:soporte.ver');
    Route::post('/', [SoporteController::class, 'store'])->middleware(['permiso:soporte.crear', 'subscription.writable']);
    Route::get('/{id}', [SoporteController::class, 'show'])->middleware('permiso:soporte.ver')->whereNumber('id');
    Route::post('/{id}/reply', [SoporteController::class, 'reply'])->middleware(['permiso:soporte.crear', 'subscription.writable'])->whereNumber('id');
});

Route::prefix('internal/web')->middleware(['web.api.key', 'throttle:60,1'])->group(function () {
    Route::post('/provision-user', [WebProvisioningController::class, 'provisionUser']);
    Route::post('/sync-plan', [WebProvisioningController::class, 'syncPlan']);
    Route::post('/sync-password', [WebProvisioningController::class, 'syncPassword']);
    Route::get('/online-users', [WebProvisioningController::class, 'onlineUsers']);

    // Catalogo de modulos asignables a un plan (fuente de verdad para el admin).
    Route::get('/module-catalog', fn () => response()->json([
        'modules' => ModuloPermisos::catalogo(),
    ]));

    Route::get('/empresas', [AdminEmpresasController::class, 'index']);
    Route::get('/empresas/{id}', [AdminEmpresasController::class, 'show']);
    Route::get('/usuarios', [AdminEmpresasController::class, 'usuarios']);

    // Acciones administrativas (write-side multitenant)
    Route::post('/empresas/{id}/suspender', [AdminEmpresasController::class, 'suspender']);
    Route::post('/empresas/{id}/activar', [AdminEmpresasController::class, 'activar']);
    Route::put('/empresas/{id}/plan', [AdminEmpresasController::class, 'cambiarPlan']);
    Route::post('/usuarios/{id}/bloquear', [AdminEmpresasController::class, 'bloquearUsuario']);
    Route::post('/usuarios/{id}/desbloquear', [AdminEmpresasController::class, 'desbloquearUsuario']);
});
