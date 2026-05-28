<?php

namespace App\Domains\Sii\Database\Seeders;

use App\Domains\Sii\Models\Catalogos\UnidadSii;
use Illuminate\Database\Seeder;

class SiiCatUnidadesSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['codigo' => 'UN',  'nombre' => 'Unidad'],
            ['codigo' => 'KG',  'nombre' => 'Kilogramo'],
            ['codigo' => 'GR',  'nombre' => 'Gramo'],
            ['codigo' => 'TON', 'nombre' => 'Tonelada'],
            ['codigo' => 'LT',  'nombre' => 'Litro'],
            ['codigo' => 'ML',  'nombre' => 'Mililitro'],
            ['codigo' => 'MT',  'nombre' => 'Metro'],
            ['codigo' => 'M2',  'nombre' => 'Metro cuadrado'],
            ['codigo' => 'M3',  'nombre' => 'Metro cúbico'],
            ['codigo' => 'CM',  'nombre' => 'Centímetro'],
            ['codigo' => 'HR',  'nombre' => 'Hora'],
            ['codigo' => 'DIA', 'nombre' => 'Día'],
            ['codigo' => 'MES', 'nombre' => 'Mes'],
            ['codigo' => 'AÑO', 'nombre' => 'Año'],
            ['codigo' => 'CJ',  'nombre' => 'Caja'],
            ['codigo' => 'DC',  'nombre' => 'Docena'],
            ['codigo' => 'PR',  'nombre' => 'Par'],
            ['codigo' => 'PQT', 'nombre' => 'Paquete'],
            ['codigo' => 'KW',  'nombre' => 'Kilowatt'],
            ['codigo' => 'KWH', 'nombre' => 'Kilowatt-hora'],
            ['codigo' => 'GAL', 'nombre' => 'Galón'],
        ];

        foreach ($registros as $registro) {
            UnidadSii::updateOrCreate(
                ['codigo' => $registro['codigo']],
                $registro
            );
        }
    }
}
