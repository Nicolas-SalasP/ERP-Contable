<?php

namespace Database\Seeders;

use App\Domains\Contabilidad\Models\TasaPpmPropyme;
use Illuminate\Database\Seeder;

class TasaPpmPropymeSeeder extends Seeder
{
    public function run(): void
    {
        $tasas = [
            ['anio' => 2024, 'tasa_base_pct' => 0.250, 'tasa_sobre_50000uf_pct' => 0.500],
            ['anio' => 2025, 'tasa_base_pct' => 0.250, 'tasa_sobre_50000uf_pct' => 0.500],
            // Rebaja transitoria Ley 21.210 (DT, arts. 14 D N°8):
            ['anio' => 2026, 'tasa_base_pct' => 0.125, 'tasa_sobre_50000uf_pct' => 0.250],
            ['anio' => 2027, 'tasa_base_pct' => 0.125, 'tasa_sobre_50000uf_pct' => 0.250],
            ['anio' => 2028, 'tasa_base_pct' => 0.125, 'tasa_sobre_50000uf_pct' => 0.250],
            ['anio' => 2029, 'tasa_base_pct' => 0.250, 'tasa_sobre_50000uf_pct' => 0.500],
            ['anio' => 2030, 'tasa_base_pct' => 0.250, 'tasa_sobre_50000uf_pct' => 0.500],
        ];

        foreach ($tasas as $tasa) {
            TasaPpmPropyme::updateOrCreate(['anio' => $tasa['anio']], $tasa);
        }
    }
}
