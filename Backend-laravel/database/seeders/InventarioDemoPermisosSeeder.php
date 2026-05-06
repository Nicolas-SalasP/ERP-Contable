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
            | Fase 1 - Productos y bodegas
            |--------------------------------------------------------------------------
            */
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',

            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            /*
            |--------------------------------------------------------------------------
            | Fase 2 - Movimientos y Kardex
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
            | Fase 3 - Valorización PMP
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
            | Fase 5 - Lotes, vencimientos y trazabilidad
            |--------------------------------------------------------------------------
            */
            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',
        ];
    }

    private function permisosAuditorInventario(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Auditor
        |--------------------------------------------------------------------------
        |
        | Solo consulta. No registra movimientos ni ajustes críticos.
        |
        */
        return [
            'inventario.productos.ver',
            'inventario.bodegas.ver',

            'inventario.movimientos.ver',
            'inventario.kardex.ver',

            'inventario.valorizacion.ver',

            'inventario.ajustes_criticos.ver',
            'inventario.lotes.ver',
        ];
    }
}