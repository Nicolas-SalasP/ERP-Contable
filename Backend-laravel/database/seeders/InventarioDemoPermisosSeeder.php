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
        ];
    }
}