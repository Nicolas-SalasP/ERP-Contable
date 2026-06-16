<?php

namespace Database\Seeders;

use App\Domains\Comercial\Models\TasaRetencionHonorarios;
use Illuminate\Database\Seeder;

class TasaRetencionHonorariosSeeder extends Seeder
{
    public function run(): void
    {
        $tasas = [
            [2020, 10.75],
            [2021, 11.50],
            [2022, 12.25],
            [2023, 13.00],
            [2024, 13.75],
            [2025, 14.50],
            [2026, 15.25],
            [2027, 16.25],
            [2028, 17.00],
        ];

        foreach ($tasas as [$anio, $tasa]) {
            TasaRetencionHonorarios::updateOrCreate(
                ['anio' => $anio],
                ['tasa_pct' => $tasa]
            );
        }
    }
}
