<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\Pais;

class PaisSeeder extends Seeder
{
    public function run(): void
    {
        Pais::firstOrCreate(
            ['iso' => 'CL'],
            ['nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]
        );
    }
}