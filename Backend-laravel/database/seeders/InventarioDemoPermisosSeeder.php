<?php

namespace Database\Seeders;

use App\Domains\Core\Models\Rol;
use Illuminate\Database\Seeder;

class InventarioDemoPermisosSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Seeder opcional para Demo/Postman de Inventario
        |--------------------------------------------------------------------------
        |
        | Este seeder NO crea roles.
        | Este seeder NO crea usuarios.
        | Este seeder NO pertenece al flujo normal de producción.
        | Este seeder NO debe agregarse al DatabaseSeeder.
        |
        | Solo asigna permisos de Inventario a los roles existentes creados
        | por RolSeeder, para facilitar pruebas Postman/demo.
        |
        | Uso manual:
        | php artisan db:seed --class=InventarioDemoPermisosSeeder
        |
        */

        $this->asignarPermisos('Super Admin', $this->permisosAdministradorInventario());
        $this->asignarPermisos('Administrador', $this->permisosAdministradorInventario());
        $this->asignarPermisos('Contador', $this->permisosContadorInventario());
        $this->asignarPermisos('Auditor', $this->permisosAuditorInventario());
    }

    private function asignarPermisos(string $nombreRol, array $permisosNuevos): void
    {
        $rol = Rol::where('nombre', $nombreRol)->first();

        if (!$rol) {
            $this->command?->warn("Rol {$nombreRol} no existe. Se omite asignación de permisos de Inventario.");
            return;
        }

        $permisosActuales = $rol->permisos ?? [];

        if (is_string($permisosActuales)) {
            $permisosActuales = json_decode($permisosActuales, true) ?: [];
        }

        if (!is_array($permisosActuales)) {
            $permisosActuales = [];
        }

        /*
        |--------------------------------------------------------------------------
        | Merge seguro
        |--------------------------------------------------------------------------
        |
        | No sobrescribe permisos existentes de otros módulos.
        | Solo agrega los permisos de Inventario que falten.
        |
        */
        $permisosActualizados = array_values(
            array_unique(
                array_merge($permisosActuales, $permisosNuevos)
            )
        );

        $rol->update([
            'permisos' => $permisosActualizados,
        ]);

        $this->command?->info("Permisos de Inventario asignados al rol {$nombreRol}.");
    }

    private function permisosAdministradorInventario(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Administrador
        |--------------------------------------------------------------------------
        |
        | Aunque AuthController ya entrega permisos runtime al administrador,
        | se asignan también para que en la demo visual aparezcan marcados.
        |
        */
        return $this->permisosContadorInventario();
    }

    private function permisosContadorInventario(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Contador
        |--------------------------------------------------------------------------
        |
        | Puede operar Inventario completo.
        |
        */
        return [
            /*
            |--------------------------------------------------------------------------
            | Fase 1 - Catálogos, productos, bodegas y stock base
            |--------------------------------------------------------------------------
            */
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',

            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            /*
            |--------------------------------------------------------------------------
            | Fase 13 a 16 - Ubicaciones, WMS, despachos y devoluciones
            |--------------------------------------------------------------------------
            */
            'inventario.ubicaciones.ver',
            'inventario.ubicaciones.crear',
            'inventario.ubicaciones.editar',
            'inventario.stock_ubicaciones.ver',
            'inventario.stock_ubicaciones.mover',
            'inventario.putaway.ejecutar',
            'inventario.picking.ver',
            'inventario.picking.crear',
            'inventario.picking.editar',
            'inventario.picking.confirmar',
            'inventario.picking.cancelar',
            'inventario.packing.ver',
            'inventario.packing.crear',
            'inventario.packing.editar',
            'inventario.packing.confirmar',
            'inventario.packing.cancelar',
            'inventario.despachos.ver',
            'inventario.despachos.crear',
            'inventario.despachos.editar',
            'inventario.despachos.confirmar',
            'inventario.despachos.cancelar',
            'inventario.devoluciones.ver',
            'inventario.devoluciones.crear',
            'inventario.devoluciones.confirmar',
            'inventario.devoluciones.cancelar',
            'inventario.reportes.picking',
            'inventario.reportes.packing',
            'inventario.reportes.despachos',
            'inventario.reportes.devoluciones',

            /*
            |--------------------------------------------------------------------------
            | Fase 17 - Auditoría y seguridad operativa
            |--------------------------------------------------------------------------
            */
            'inventario.auditoria.ver',
            'inventario.auditoria.detalle',
            'inventario.auditoria.resumen',
            'inventario.seguridad.ver',
            'inventario.eventos_integracion.ver',
            'inventario.eventos_integracion.detalle',
            'inventario.eventos_integracion.resumen',
            'inventario.eventos_integracion.procesar',
            'inventario.eventos_integracion.gestionar',

            /*
            |--------------------------------------------------------------------------
            | Fase 2 - Movimientos de inventario y Kardex
            |--------------------------------------------------------------------------
            */
            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',

            'inventario.kardex.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 3 - PMP y valorización
            |--------------------------------------------------------------------------
            */
            'inventario.valorizacion.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 9/10 - Dashboard y reportes
            |--------------------------------------------------------------------------
            */
            'inventario.dashboard.ver',
            'inventario.reportes.ver',
            'inventario.reportes.exportar',

            /*
            |--------------------------------------------------------------------------
            | Fase 4 - Mermas y ajustes críticos
            |--------------------------------------------------------------------------
            */
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',

            /*
            |--------------------------------------------------------------------------
            | Fase 5 - Lotes, vencimientos y trazabilidad avanzada
            |--------------------------------------------------------------------------
            */
            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',

            /*
            |--------------------------------------------------------------------------
            | Fase 6 - Reservas y disponibilidad comprometida
            |--------------------------------------------------------------------------
            */
            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 8 - Reposición y alertas
            |--------------------------------------------------------------------------
            */
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver',
            'inventario.reglas_reposicion.crear',
            'inventario.reglas_reposicion.editar',
            'inventario.reglas_reposicion.eliminar',

            /*
            |--------------------------------------------------------------------------
            | Fase 7 - Toma física e inventario cíclico
            |--------------------------------------------------------------------------
            */
            'inventario.tomas_fisicas.ver',
            'inventario.tomas_fisicas.crear',
            'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar',
            'inventario.tomas_fisicas.ajustar',
            'inventario.tomas_fisicas.cancelar',
        ];
    }

    private function permisosAuditorInventario(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Auditor
        |--------------------------------------------------------------------------
        |
        | Solo consulta. No registra movimientos, ajustes críticos, lotes ni reservas.
        |
        */
        return [
            /*
            |--------------------------------------------------------------------------
            | Fase 1 - Consulta base
            |--------------------------------------------------------------------------
            */
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.ubicaciones.ver',
            'inventario.stock_ubicaciones.ver',
            'inventario.picking.ver',
            'inventario.packing.ver',
            'inventario.despachos.ver',
            'inventario.devoluciones.ver',
            'inventario.reportes.picking',
            'inventario.reportes.packing',
            'inventario.reportes.despachos',
            'inventario.reportes.devoluciones',

            /*
            |--------------------------------------------------------------------------
            | Fase 17 - Consulta de auditoría operativa
            |--------------------------------------------------------------------------
            */
            'inventario.auditoria.ver',
            'inventario.auditoria.detalle',
            'inventario.auditoria.resumen',
            'inventario.eventos_integracion.ver',
            'inventario.eventos_integracion.detalle',
            'inventario.eventos_integracion.resumen',

            /*
            |--------------------------------------------------------------------------
            | Fase 2 - Consulta de movimientos y Kardex
            |--------------------------------------------------------------------------
            */
            'inventario.movimientos.ver',
            'inventario.kardex.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 3 - Consulta de valorización
            |--------------------------------------------------------------------------
            */
            'inventario.valorizacion.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 9/10 - Consulta dashboard y reportes
            |--------------------------------------------------------------------------
            */
            'inventario.dashboard.ver',
            'inventario.reportes.ver',
            'inventario.reportes.exportar',

            /*
            |--------------------------------------------------------------------------
            | Fase 4 - Consulta de ajustes críticos
            |--------------------------------------------------------------------------
            */
            'inventario.ajustes_criticos.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 5 - Consulta de lotes
            |--------------------------------------------------------------------------
            */
            'inventario.lotes.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 6 - Consulta de reservas y disponibilidad
            |--------------------------------------------------------------------------
            */
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 8 - Consulta de alertas y reposición
            |--------------------------------------------------------------------------
            */
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver',
            
            /*
            |--------------------------------------------------------------------------
            | Fase 7 - Consulta de toma física
            |--------------------------------------------------------------------------
            */
            'inventario.tomas_fisicas.ver',
        ];
    }
}