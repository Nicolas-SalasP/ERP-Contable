<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permisosFase2 = [
        'inventario.movimientos.ver',
        'inventario.movimientos.entrada',
        'inventario.movimientos.salida',
        'inventario.movimientos.traspaso',
        'inventario.movimientos.ajuste',
        'inventario.kardex.ver',
    ];

    public function up(): void
    {
        $permisosPorRol = [
            'Administrador' => [
                'inventario.movimientos.ver',
                'inventario.movimientos.entrada',
                'inventario.movimientos.salida',
                'inventario.movimientos.traspaso',
                'inventario.movimientos.ajuste',
                'inventario.kardex.ver',
            ],

            /*
            |--------------------------------------------------------------------------
            | Contador
            |--------------------------------------------------------------------------
            |
            | Puede operar inventario completo en esta etapa.
            | Si después quieren separar responsabilidades, se puede ajustar.
            |
            */
            'Contador' => [
                'inventario.movimientos.ver',
                'inventario.movimientos.entrada',
                'inventario.movimientos.salida',
                'inventario.movimientos.traspaso',
                'inventario.movimientos.ajuste',
                'inventario.kardex.ver',
            ],

            /*
            |--------------------------------------------------------------------------
            | Auditor
            |--------------------------------------------------------------------------
            |
            | Solo consulta movimientos y kardex.
            | No registra entradas, salidas, traspasos ni ajustes.
            |
            */
            'Auditor' => [
                'inventario.movimientos.ver',
                'inventario.kardex.ver',
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
                    fn (string $permiso): bool => !in_array($permiso, $this->permisosFase2, true)
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