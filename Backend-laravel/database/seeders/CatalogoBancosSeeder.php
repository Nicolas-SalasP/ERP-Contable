<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoBancosSeeder extends Seeder
{
    public function run(): void
    {
        $bancos = [
            ['nombre' => 'Banco de Chile'],
            ['nombre' => 'Banco Internacional'],
            ['nombre' => 'Banco Estado'],
            ['nombre' => 'Scotiabank Chile'],
            ['nombre' => 'Banco de Crédito e Inversiones (BCI)'],
            ['nombre' => 'Banco BICE'],
            ['nombre' => 'Banco Santander-Chile'],
            ['nombre' => 'Banco Itaú'],
            ['nombre' => 'Banco Security'],
            ['nombre' => 'Banco Falabella'],
            ['nombre' => 'Banco Ripley'],
            ['nombre' => 'Banco Consorcio'],
            ['nombre' => 'Banco BTG Pactual Chile'],
            ['nombre' => 'Coopeuch'],
            ['nombre' => 'Tenpo Prepago'],
            ['nombre' => 'Mercado Pago'],
            ['nombre' => 'Caja Los Andes'],
        ];

        DB::table('catalogo_bancos')->truncate();

        foreach ($bancos as $banco) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => $banco['nombre']
            ]);
        }
    }
}