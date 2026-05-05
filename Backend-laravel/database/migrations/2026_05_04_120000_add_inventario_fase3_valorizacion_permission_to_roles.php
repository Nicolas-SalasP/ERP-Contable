<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permisosFase3 = [
        'inventario.valorizacion.ver',
    ];

    public function up(): void
    {
        $permisosPorRol = [
            /*
            |--------------------------------------------------------------------------
            | Administrador
            |--------------------------------------------------------------------------
            |
            | Aunque InventarioPermisoService permite todo al Administrador por nombre
            | de rol, se agrega igualmente para mantener trazabilidad explícita en JSON.
            |
            */
            'Administrador' => [
                'inventario.valorizacion.ver',
            ],

            /*
            |--------------------------------------------------------------------------
            | Contador
            |--------------------------------------------------------------------------
            |
            | Puede consultar stock valorizado, PMP por bodega y resumen valorizado.
            |
            */
            'Contador' => [
                'inventario.valorizacion.ver',
            ],

            /*
            |--------------------------------------------------------------------------
            | Auditor
            |--------------------------------------------------------------------------
            |
            | Puede consultar valorización, igual que movimientos y kardex.
            | No se le agregan permisos de creación ni modificación de movimientos.
            |
            */
            'Auditor' => [
                'inventario.valorizacion.ver',
            ],
        ];

        foreach ($permisosPorRol as $nombreRol => $permisosNuevos) {
            $this->agregarPermisosARol($nombreRol, $permisosNuevos);
        }
    }

    public function down(): void
    {
        foreach (['Administrador', 'Contador', 'Auditor'] as $nombreRol) {
            $rol = DB::table('roles')->where('nombre', $nombreRol)->first();

            if (!$rol) {
                continue;
            }

            $permisosActuales = $rol->permisos
                ? json_decode($rol->permisos, true)
                : [];

            if (!is_array($permisosActuales)) {
                $permisosActuales = [];
            }

            $permisosActualizados = array_values(
                array_filter(
                    $permisosActuales,
                    fn (string $permiso): bool => !in_array($permiso, $this->permisosFase3, true)
                )
            );

            DB::table('roles')->where('id', $rol->id)->update([
                'permisos' => json_encode($permisosActualizados),
            ]);
        }
    }

    private function agregarPermisosARol(string $nombreRol, array $permisosNuevos): void
    {
        $rol = DB::table('roles')->where('nombre', $nombreRol)->first();

        if (!$rol) {
            return;
        }

        $permisosActuales = $rol->permisos
            ? json_decode($rol->permisos, true)
            : [];

        if (!is_array($permisosActuales)) {
            $permisosActuales = [];
        }

        $permisosActualizados = array_values(
            array_unique(
                array_merge($permisosActuales, $permisosNuevos)
            )
        );

        DB::table('roles')->where('id', $rol->id)->update([
            'permisos' => json_encode($permisosActualizados),
        ]);
    }
};