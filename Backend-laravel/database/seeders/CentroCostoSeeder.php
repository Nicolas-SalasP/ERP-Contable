<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Contabilidad\Models\CentroCosto;

class CentroCostoSeeder extends Seeder
{
    public function run(): void
    {
        $centros = [
            'ADM' => 'Administración Central',
            'VTA' => 'Ventas y Comercial',
            'OPR' => 'Operaciones y Logística'
        ];

        foreach ($centros as $codigo => $nombre) {
            CentroCosto::firstOrCreate(
                ['codigo' => $codigo, 'empresa_id' => 1],
                ['nombre' => $nombre, 'activo' => true]
            );
        }
    }
}