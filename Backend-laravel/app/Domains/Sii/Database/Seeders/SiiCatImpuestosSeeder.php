<?php

namespace App\Domains\Sii\Database\Seeders;

use App\Domains\Sii\Models\Catalogos\ImpuestoSii;
use Illuminate\Database\Seeder;

class SiiCatImpuestosSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['codigo' => 14,  'nombre' => 'IVA',                                                'tasa' => 19.00, 'tipo' => 'iva',         'es_adicional' => false],
            ['codigo' => 15,  'nombre' => 'IVA Retenido Total',                                 'tasa' => 19.00, 'tipo' => 'retencion',   'es_adicional' => true],
            ['codigo' => 17,  'nombre' => 'IVA Retenido Parcial',                               'tasa' => null,  'tipo' => 'retencion',   'es_adicional' => true],
            ['codigo' => 23,  'nombre' => 'Impuesto Adicional Bebidas Alcohólicas (vinos)',     'tasa' => 20.50, 'tipo' => 'ila',         'es_adicional' => true],
            ['codigo' => 24,  'nombre' => 'Impuesto Adicional Bebidas Destiladas',              'tasa' => 31.50, 'tipo' => 'ila',         'es_adicional' => true],
            ['codigo' => 25,  'nombre' => 'Vinos',                                              'tasa' => 20.50, 'tipo' => 'ila',         'es_adicional' => true],
            ['codigo' => 26,  'nombre' => 'Cervezas',                                           'tasa' => 20.50, 'tipo' => 'ila',         'es_adicional' => true],
            ['codigo' => 27,  'nombre' => 'Bebidas Analcohólicas con Azúcar',                   'tasa' => 18.00, 'tipo' => 'ila',         'es_adicional' => true],
            ['codigo' => 271, 'nombre' => 'Bebidas Analcohólicas sin Azúcar',                   'tasa' => 10.00, 'tipo' => 'ila',         'es_adicional' => true],
            ['codigo' => 28,  'nombre' => 'Impuesto Específico Gasolina',                       'tasa' => null,  'tipo' => 'especifico',  'es_adicional' => true],
            ['codigo' => 30,  'nombre' => 'Impuesto Específico Diésel',                         'tasa' => null,  'tipo' => 'especifico',  'es_adicional' => true],
            ['codigo' => 32,  'nombre' => 'Cigarros',                                           'tasa' => 52.60, 'tipo' => 'especifico',  'es_adicional' => true],
            ['codigo' => 33,  'nombre' => 'Cigarrillos',                                        'tasa' => null,  'tipo' => 'especifico',  'es_adicional' => true],
            ['codigo' => 34,  'nombre' => 'Tabaco Elaborado',                                   'tasa' => 59.70, 'tipo' => 'especifico',  'es_adicional' => true],
            ['codigo' => 50,  'nombre' => 'Impuesto Adicional Productos Suntuarios',            'tasa' => 15.00, 'tipo' => 'otro',        'es_adicional' => true],
        ];

        foreach ($registros as $registro) {
            ImpuestoSii::updateOrCreate(
                ['codigo' => $registro['codigo']],
                $registro
            );
        }
    }
}
