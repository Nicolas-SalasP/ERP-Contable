<?php

namespace App\Domains\Core\Support;

use App\Domains\Core\Models\User;

final class ModuloPermisos
{
    /**
     * Modulos de sistema/cumplimiento que no dependen del catalogo de planes de tenri.cl.
     * Un plan viejo (creado antes de que el modulo existiera) no debe poder ocultarlo.
     */
    public const MODULOS_SIEMPRE_DISPONIBLES = ['alertas'];

    public const MAP = [
        'dashboard' => ['dashboard.ver'],
        'alertas' => ['alertas.ver', 'alertas.gestionar'],
        'clientes' => ['ventas.ver', 'clientes.ver', 'clientes.crear'],
        'cotizaciones' => ['ventas.ver', 'ventas.crear'],
        'facturas.manual' => ['compras.ver', 'compras.crear'],
        'facturas.historial' => ['compras.ver'],
        'facturas.auditoria' => ['compras.ver'],
        'dte.emision' => ['ventas.ver', 'ventas.crear'],
        'documentos.anulacion' => ['ventas.ver', 'compras.ver'],
        'contabilidad.plan_cuentas' => ['contabilidad.ver', 'contabilidad.crear'],
        'contabilidad.libro_mayor' => ['contabilidad.ver'],
        'contabilidad.asientos' => ['contabilidad.ver', 'contabilidad.crear'],
        'contabilidad.visor' => ['contabilidad.ver'],
        'contabilidad.reclasificador' => ['contabilidad.ver', 'contabilidad.crear'],
        'contabilidad.cierre_periodo' => ['contabilidad.ver', 'contabilidad.cerrar_periodo'],
        'proveedores' => ['compras.ver', 'proveedores.ver', 'proveedores.crear'],
        'tesoreria.cartola' => ['tesoreria.ver'],
        'tesoreria.conciliacion' => ['tesoreria.ver', 'tesoreria.crear'],
        'tesoreria.nomina' => ['tesoreria.ver', 'tesoreria.crear'],
        'inventario.dashboard' => ['inventario.dashboard.ver'],
        'inventario.reportes' => ['inventario.reportes.ver', 'inventario.reportes.exportar'],
        'inventario.productos' => ['inventario.productos.ver', 'inventario.productos.crear', 'inventario.productos.editar'],
        'inventario.bodegas' => ['inventario.bodegas.ver', 'inventario.bodegas.crear'],
        'inventario.ubicaciones' => ['inventario.ubicaciones.ver', 'inventario.ubicaciones.crear', 'inventario.ubicaciones.editar'],
        'inventario.stock_ubicaciones' => ['inventario.stock_ubicaciones.ver', 'inventario.stock_ubicaciones.mover', 'inventario.putaway.ejecutar'],
        'inventario.picking' => ['inventario.picking.ver', 'inventario.picking.crear', 'inventario.picking.editar', 'inventario.picking.confirmar', 'inventario.picking.cancelar'],
        'inventario.packing' => ['inventario.packing.ver', 'inventario.packing.crear', 'inventario.packing.editar', 'inventario.packing.confirmar', 'inventario.packing.cancelar'],
        'inventario.despachos' => ['inventario.despachos.ver', 'inventario.despachos.crear', 'inventario.despachos.editar', 'inventario.despachos.confirmar', 'inventario.despachos.cancelar'],
        'inventario.devoluciones' => ['inventario.devoluciones.ver', 'inventario.devoluciones.crear', 'inventario.devoluciones.confirmar', 'inventario.devoluciones.cancelar'],
        'inventario.auditoria' => ['inventario.auditoria.ver', 'inventario.auditoria.detalle', 'inventario.auditoria.resumen', 'inventario.seguridad.ver'],
        'inventario.eventos_integracion' => ['inventario.eventos_integracion.ver', 'inventario.eventos_integracion.detalle', 'inventario.eventos_integracion.resumen', 'inventario.eventos_integracion.procesar', 'inventario.eventos_integracion.gestionar'],
        'inventario.reportes_operacion_bodega' => ['inventario.reportes.picking', 'inventario.reportes.packing', 'inventario.reportes.despachos', 'inventario.reportes.devoluciones'],
        'inventario.movimientos' => ['inventario.movimientos.ver', 'inventario.movimientos.entrada', 'inventario.movimientos.salida', 'inventario.movimientos.traspaso', 'inventario.movimientos.ajuste'],
        'inventario.kardex' => ['inventario.kardex.ver'],
        'inventario.ajustes_criticos' => ['inventario.ajustes_criticos.ver', 'inventario.ajustes_criticos.crear'],
        'inventario.lotes' => ['inventario.lotes.ver', 'inventario.lotes.crear', 'inventario.lotes.editar'],
        'inventario.reservas' => ['inventario.reservas.ver', 'inventario.reservas.crear', 'inventario.reservas.cancelar', 'inventario.reservas.liberar', 'inventario.reservas.consumir'],
        'inventario.disponibilidad' => ['inventario.disponibilidad.ver'],
        'inventario.valorizacion' => ['inventario.valorizacion.ver'],
        'inventario.alertas' => ['inventario.alertas.ver'],
        'inventario.reglas_reposicion' => ['inventario.reglas_reposicion.ver', 'inventario.reglas_reposicion.crear', 'inventario.reglas_reposicion.editar', 'inventario.reglas_reposicion.eliminar'],
        'inventario.tomas_fisicas' => ['inventario.tomas_fisicas.ver', 'inventario.tomas_fisicas.crear', 'inventario.tomas_fisicas.contar', 'inventario.tomas_fisicas.cerrar', 'inventario.tomas_fisicas.ajustar', 'inventario.tomas_fisicas.cancelar'],
        'activos_fijos' => ['activos.ver', 'activos.crear'],
        'tributario.renta' => ['tributario.ver'],
        'tributario.mapeo_sii' => ['tributario.ver'],
        'tributario.f29' => ['tributario.ver', 'tributario.crear'],
        'usuarios.gestion' => ['usuarios.ver', 'usuarios.gestionar'],
        'sii.configuracion' => ['sii.configuracion.ver', 'sii.configuracion.editar'],
        'sii.certificado' => ['sii.certificado.ver', 'sii.certificado.subir', 'sii.certificado.revocar'],
        'sii.caf' => ['sii.caf.ver', 'sii.caf.subir', 'sii.caf.revocar'],
        'sii.dte' => ['sii.dte.ver', 'sii.dte.emitir', 'sii.dte.reintentar', 'sii.dte.anular'],
        'sii.auditoria' => ['sii.auditoria.ver'],
        'roles.gestion' => ['usuarios.ver', 'usuarios.gestionar'],
        'rrhh.empleados' => ['rrhh.empleados.ver', 'rrhh.empleados.crear', 'rrhh.empleados.editar'],
        'rrhh.contratos' => ['rrhh.empleados.ver', 'rrhh.contratos.crear'],
        'rrhh.remuneraciones' => ['rrhh.remuneraciones.ver', 'rrhh.remuneraciones.procesar'],
        'rrhh.finiquitos' => ['rrhh.remuneraciones.ver', 'rrhh.remuneraciones.procesar'],
        'rrhh.vacaciones' => ['rrhh.remuneraciones.ver', 'rrhh.remuneraciones.procesar'],
        'rrhh.parametros' => ['rrhh.parametros.ver', 'rrhh.parametros.editar'],
        'rrhh.centralizacion' => ['rrhh.remuneraciones.ver', 'rrhh.remuneraciones.procesar'],
        'rrhh.previred' => ['rrhh.remuneraciones.ver', 'rrhh.remuneraciones.procesar'],
        'contabilidad.dj' => ['contabilidad.dj.ver', 'contabilidad.dj.procesar'],
        'empresa.perfil' => [],
        'glosario' => [],
        'integraciones.api' => ['integraciones.api.ver', 'integraciones.api.gestionar'],
        'dashboard.ejecutivo' => ['contabilidad.ver', 'tesoreria.ver', 'ventas.ver'],
        'white_label' => [],
        'modulos.custom' => [],
        'soporte.tickets' => ['soporte.ver', 'soporte.crear'],
    ];

    /** Metadatos de presentación por módulo [label, categoria], capa visual sobre MAP (la fuente real); sin entrada acá usa label humanizado y categoria 'General'. */
    private const META = [
        'dashboard' => ['Dashboard principal', 'General'],
        'alertas' => ['Central de alertas', 'General'],
        'dashboard.ejecutivo' => ['Dashboard ejecutivo', 'General'],
        'empresa.perfil' => ['Perfil de empresa', 'General'],
        'glosario' => ['Glosario contable', 'General'],
        'clientes' => ['Clientes y directorio', 'Comercial'],
        'cotizaciones' => ['Cotizaciones', 'Comercial'],
        'facturas.manual' => ['Ingreso de facturas', 'Compras'],
        'facturas.historial' => ['Historial de compras', 'Compras'],
        'facturas.auditoria' => ['Auditoría de facturas', 'Compras'],
        'dte.emision' => ['Emisión DTE', 'Compras'],
        'documentos.anulacion' => ['Anulación de documentos', 'Compras'],
        'proveedores' => ['Proveedores', 'Compras'],
        'tesoreria.cartola' => ['Cartola bancaria', 'Tesorería'],
        'tesoreria.conciliacion' => ['Mesa de conciliación', 'Tesorería'],
        'tesoreria.nomina' => ['Nómina de pagos', 'Tesorería'],
        'contabilidad.plan_cuentas' => ['Plan de cuentas', 'Contabilidad'],
        'contabilidad.libro_mayor' => ['Libro mayor', 'Contabilidad'],
        'contabilidad.asientos' => ['Asientos manuales', 'Contabilidad'],
        'contabilidad.visor' => ['Visor de asientos', 'Contabilidad'],
        'contabilidad.reclasificador' => ['Reclasificador', 'Contabilidad'],
        'contabilidad.cierre_periodo' => ['Cierre de periodo', 'Contabilidad'],
        'activos_fijos' => ['Activos fijos', 'Activos'],
        'inventario.dashboard' => ['Dashboard de inventario', 'Inventario'],
        'inventario.reportes' => ['Reportes de inventario', 'Inventario'],
        'inventario.productos' => ['Productos', 'Inventario'],
        'inventario.bodegas' => ['Bodegas', 'Inventario'],
        'inventario.ubicaciones' => ['Ubicaciones', 'Inventario'],
        'inventario.stock_ubicaciones' => ['Stock por ubicación', 'Inventario'],
        'inventario.picking' => ['Picking', 'Inventario'],
        'inventario.packing' => ['Packing', 'Inventario'],
        'inventario.despachos' => ['Despachos', 'Inventario'],
        'inventario.devoluciones' => ['Devoluciones', 'Inventario'],
        'inventario.auditoria' => ['Auditoría operativa', 'Inventario'],
        'inventario.eventos_integracion' => ['Eventos de integración', 'Inventario'],
        'inventario.reportes_operacion_bodega' => ['Reportes de operación de bodega', 'Inventario'],
        'inventario.movimientos' => ['Movimientos', 'Inventario'],
        'inventario.kardex' => ['Kardex', 'Inventario'],
        'inventario.ajustes_criticos' => ['Ajustes críticos', 'Inventario'],
        'inventario.lotes' => ['Lotes', 'Inventario'],
        'inventario.reservas' => ['Reservas', 'Inventario'],
        'inventario.disponibilidad' => ['Disponibilidad', 'Inventario'],
        'inventario.valorizacion' => ['Valorización', 'Inventario'],
        'inventario.alertas' => ['Alertas y reposición', 'Inventario'],
        'inventario.reglas_reposicion' => ['Reglas de reposición', 'Inventario'],
        'inventario.tomas_fisicas' => ['Tomas físicas', 'Inventario'],
        'tributario.renta' => ['Operación renta', 'Tributario'],
        'tributario.mapeo_sii' => ['Mapeo SII', 'Tributario'],
        'tributario.f29' => ['Cierre F29', 'Tributario'],
        'sii.configuracion' => ['Configuración SII', 'SII'],
        'sii.certificado' => ['Certificado digital', 'SII'],
        'sii.caf' => ['Folios CAF', 'SII'],
        'sii.dte' => ['Emisión y estado DTE', 'SII'],
        'sii.auditoria' => ['Auditoría SII', 'SII'],
        'usuarios.gestion' => ['Gestión de usuarios', 'Administración'],
        'roles.gestion' => ['Roles y permisos', 'Administración'],
        'rrhh.empleados' => ['Ficha de personal', 'RRHH'],
        'rrhh.contratos' => ['Contratos', 'RRHH'],
        'rrhh.remuneraciones' => ['Liquidaciones de sueldo', 'RRHH'],
        'rrhh.finiquitos' => ['Finiquitos', 'RRHH'],
        'rrhh.vacaciones' => ['Vacaciones', 'RRHH'],
        'rrhh.parametros' => ['Parámetros previsionales', 'RRHH'],
        'rrhh.centralizacion' => ['Centralización contable RRHH', 'RRHH'],
        'rrhh.previred' => ['Archivo Previred', 'RRHH'],
        'integraciones.api' => ['Integraciones API', 'Enterprise'],
        'white_label' => ['White-label', 'Enterprise'],
        'modulos.custom' => ['Módulos a medida', 'Enterprise'],
        'soporte.tickets' => ['Tickets de soporte', 'Soporte'],
    ];

    /** Catálogo de módulos asignables a un plan [{key, label, categoria}], derivado de MAP por lo que nunca se desincroniza. */
    public static function catalogo(): array
    {
        return array_map(static fn (string $key): array => [
            'key' => $key,
            'label' => self::META[$key][0] ?? ucfirst(str_replace(['.', '_'], ' ', $key)),
            'categoria' => self::META[$key][1] ?? 'General',
        ], array_keys(self::MAP));
    }

    public static function permisosUsuario(User $usuario): array
    {
        $usuario->loadMissing('rol');

        $moduleKeys = self::normalizarLista($usuario->module_keys ?? []);
        $permisosRol = self::normalizarLista($usuario->rol->permisos ?? []);
        $permisosModulos = self::permisosDesdeModulos($moduleKeys);

        // SuperAdmin / staff interno (jerarquia >= 100): sin tope de plan.
        if ($usuario->rol && (int) ($usuario->rol->jerarquia ?? 0) >= 100) {
            return self::normalizarLista(array_merge(
                self::todosLosPermisos(),
                $permisosRol,
                $permisosModulos
            ));
        }

        $base = self::esAdministradorOperativo($usuario)
            ? array_merge($permisosRol, $permisosModulos, self::permisosInventarioCompletos())
            : array_merge($permisosRol, $permisosModulos);

        $base = self::normalizarLista($base);

        // Plan como techo: si el usuario viene de un plan (module_keys no vacio), sus permisos se limitan a los del plan sin importar lo que conceda el rol.
        if (! empty($moduleKeys)) {
            $permisosSiempre = self::permisosDesdeModulos(self::MODULOS_SIEMPRE_DISPONIBLES);
            $base = array_values(array_unique(array_merge(
                array_intersect($base, $permisosModulos),
                array_intersect($base, $permisosSiempre)
            )));
        }

        return self::normalizarLista($base);
    }

    public static function permisosDesdeModulos(mixed $moduleKeys): array
    {
        $moduleKeys = self::normalizarLista($moduleKeys);
        $permisos = [];

        foreach ($moduleKeys as $key) {
            $permisos = array_merge($permisos, self::MAP[$key] ?? []);
        }

        return self::normalizarLista($permisos);
    }

    public static function todosLosPermisos(): array
    {
        return self::normalizarLista(array_merge(...array_values(self::MAP)));
    }

    public static function permisosInventarioCompletos(): array
    {
        $permisos = [];

        foreach (self::MAP as $moduleKey => $modulePermisos) {
            if (str_starts_with($moduleKey, 'inventario.')) {
                $permisos = array_merge($permisos, $modulePermisos);
            }
        }

        return self::normalizarLista($permisos);
    }

    public static function normalizarLista(mixed $valores): array
    {
        if (is_string($valores)) {
            $decoded = json_decode($valores, true);
            $valores = json_last_error() === JSON_ERROR_NONE ? $decoded : [$valores];
        }

        if (! is_array($valores)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($valor) => is_string($valor) ? trim($valor) : null,
            $valores
        ), static fn ($valor) => $valor !== null && $valor !== '')));
    }

    private static function esAdministradorOperativo(User $usuario): bool
    {
        $rol = $usuario->rol;

        if (! $rol) {
            return false;
        }

        // Solo la jerarquia real habilita el set de administrador; antes bastaba el ROL llamarse "administrador"/"admin", permitiendo escalar privilegios con un rol de jerarquia baja autoasignado.
        return (int) ($rol->jerarquia ?? 0) >= 80;
    }
}
