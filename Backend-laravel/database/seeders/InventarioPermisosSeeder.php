<?php

namespace Database\Seeders;

use App\Domains\Core\Models\Rol;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class InventarioPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $this->asegurarUnidadMedidaBase();

        $this->agregarPermisos('Administrador', $this->permisosAdministrador());
        $this->agregarPermisos('Contador', $this->permisosContador());
        $this->agregarPermisos('Auditor', $this->permisosAuditor());
    }

    private function asegurarUnidadMedidaBase(): void
    {
        UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            [
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
            ]
        );
    }

    private function agregarPermisos(string $nombreRol, array $permisosNuevos): void
    {
        $rol = Rol::firstOrCreate([
            'nombre' => $nombreRol,
        ]);

        $permisosActuales = $rol->permisos ?? [];

        if (is_string($permisosActuales)) {
            $permisosActuales = json_decode($permisosActuales, true) ?: [];
        }

        if (!is_array($permisosActuales)) {
            $permisosActuales = [];
        }

        $rol->permisos = array_values(array_unique(array_merge(
            $permisosActuales,
            $permisosNuevos
        )));

        $rol->save();
    }

    private function permisosAdministrador(): array
    {
        return array_values(array_unique(array_merge(
            $this->permisosInventarioOperacion(),
            $this->permisosInventarioConsulta()
        )));
    }

    private function permisosContador(): array
    {
        return array_values(array_unique(array_merge(
            $this->permisosInventarioOperacion(),
            $this->permisosInventarioConsulta()
        )));
    }

    private function permisosAuditor(): array
    {
        return $this->permisosInventarioConsulta();
    }

    private function permisosInventarioOperacion(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Fase 1 - Operación de productos y bodegas
            |--------------------------------------------------------------------------
            */
            'inventario.productos.crear',
            'inventario.productos.editar',
            'inventario.bodegas.crear',

            /*
            |--------------------------------------------------------------------------
            | Fase 2 - Operación de movimientos
            |--------------------------------------------------------------------------
            */
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',
        ];
    }

    private function permisosInventarioConsulta(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Fase 1 - Consulta
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
            | Fase 3 - Precio Medio Ponderado / Valorización
            |--------------------------------------------------------------------------
            |
            | Inventario NO emite, gestiona ni prepara DTE.
            | Este permiso solo permite consultar stock valorizado.
            |
            */
            'inventario.valorizacion.ver',
        ];
    }
}