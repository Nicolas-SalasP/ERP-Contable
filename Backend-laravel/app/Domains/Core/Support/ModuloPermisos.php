<?php

namespace App\Domains\Core\Support;

use App\Domains\Core\Models\User;

final class ModuloPermisos
{
    /**
     * Mapa unico de modulos front/provisioning hacia permisos efectivos.
     *
     * Mantener esta lista centralizada evita que AuthController, frontend y
     * servicios de dominio deriven permisos con reglas distintas.
     */
    public const MAP = [
        'dashboard' => ['dashboard.ver'],
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
        'roles.gestion' => ['usuarios.ver', 'usuarios.gestionar'],
        'empresa.perfil' => [],
        'glosario' => [],
        'integraciones.api' => [],
        'dashboard.ejecutivo' => ['contabilidad.ver', 'tesoreria.ver', 'ventas.ver'],
        'white_label' => [],
        'modulos.custom' => [],
    ];

    public static function permisosUsuario(User $usuario): array
    {
        $usuario->loadMissing('rol');

        $permisosRol = self::normalizarLista($usuario->rol?->permisos ?? []);
        $permisosModulos = self::permisosDesdeModulos($usuario->module_keys ?? []);

        if ($usuario->rol && (int) ($usuario->rol->jerarquia ?? 0) >= 100) {
            return self::normalizarLista(array_merge(
                self::todosLosPermisos(),
                $permisosRol,
                $permisosModulos
            ));
        }

        if (self::esAdministradorOperativo($usuario)) {
            return self::normalizarLista(array_merge(
                $permisosRol,
                $permisosModulos,
                self::permisosInventarioCompletos()
            ));
        }

        return self::normalizarLista(array_merge($permisosRol, $permisosModulos));
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

        if (!is_array($valores)) {
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

        if (!$rol) {
            return false;
        }

        $jerarquia = (int) ($rol->jerarquia ?? 0);
        $nombreRol = strtolower(trim((string) ($rol->nombre ?? '')));

        return $jerarquia >= 80 || in_array($nombreRol, [
            'administrador',
            'admin',
            'super admin',
            'superadmin',
        ], true);
    }
}
