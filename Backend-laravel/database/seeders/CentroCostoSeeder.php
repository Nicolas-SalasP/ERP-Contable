<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Contabilidad\Models\CentroCosto;
use App\Domains\Core\Models\Empresa;

class CentroCostoSeeder extends Seeder
{
    public function run(): void
    {
        // Asumimos que inyectamos los centros de costo a la primera empresa (Tenri SpA)
        $empresa = Empresa::first();

        if (!$empresa) return;

        $centros = [
            ['codigo' => '6000', 'nombre' => 'Produccion'],
            ['codigo' => '6200', 'nombre' => 'Operaciones'],
            ['codigo' => '6300', 'nombre' => 'Bodega'],
            ['codigo' => '6400', 'nombre' => 'Mantenimiento'],
            ['codigo' => '6500', 'nombre' => 'Calidad'],
            ['codigo' => '6600', 'nombre' => 'Aseo'],
            ['codigo' => '7000', 'nombre' => 'Ventas'],
            ['codigo' => '7010', 'nombre' => 'Marketing'],
            ['codigo' => '8010', 'nombre' => 'Administracion y Finanzas'],
            ['codigo' => '8020', 'nombre' => 'Informatica'],
            ['codigo' => '9000', 'nombre' => 'Costos Financieros'],
        ];

        foreach ($centros as $cc) {
            CentroCosto::firstOrCreate([
                'empresa_id' => $empresa->id,
                'codigo' => $cc['codigo']
            ], [
                'nombre' => $cc['nombre'],
                'activo' => true
            ]);
        }
    }
}