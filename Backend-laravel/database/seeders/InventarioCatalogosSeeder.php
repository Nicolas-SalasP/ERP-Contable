<?php

namespace Database\Seeders;

use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class InventarioCatalogosSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Catálogos base de Inventario
        |--------------------------------------------------------------------------
        |
        | Este seeder NO asigna permisos a roles.
        | Los permisos se asignan desde el gestor visual de roles.
        |
        */
        $this->asegurarUnidadesMedidaBase();
    }

    private function asegurarUnidadesMedidaBase(): void
    {
        $unidades = [
            [
                'codigo' => 'UN',
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
            ],
            [
                'codigo' => 'KG',
                'nombre' => 'Kilogramo',
                'permite_decimal' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'LT',
                'nombre' => 'Litro',
                'permite_decimal' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'M',
                'nombre' => 'Metro',
                'permite_decimal' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'M2',
                'nombre' => 'Metro cuadrado',
                'permite_decimal' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'M3',
                'nombre' => 'Metro cúbico',
                'permite_decimal' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'HR',
                'nombre' => 'Hora',
                'permite_decimal' => true,
                'activo' => true,
            ],
            [
                'codigo' => 'CJ',
                'nombre' => 'Caja',
                'permite_decimal' => false,
                'activo' => true,
            ],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::firstOrCreate(
                ['codigo' => $unidad['codigo']],
                [
                    'nombre' => $unidad['nombre'],
                    'permite_decimal' => $unidad['permite_decimal'],
                    'activo' => $unidad['activo'],
                ]
            );
        }
    }
}