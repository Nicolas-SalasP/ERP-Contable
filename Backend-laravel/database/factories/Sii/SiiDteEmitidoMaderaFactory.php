<?php

namespace Database\Factories\Sii;

use App\Domains\Sii\Models\SiiDteEmitidoMadera;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiiDteEmitidoMadera>
 */
class SiiDteEmitidoMaderaFactory extends Factory
{
    protected $model = SiiDteEmitidoMadera::class;

    public function definition(): array
    {
        return [
            'dte_emitido_traslado_id' => SiiDteEmitidoTraslado::factory(),
            'rol_predio_origen'       => '123-456',
            'rol_predio_destino'      => '789-012',
            'codigo_plan_conaf'       => 'PLAN-2026-' . random_int(100, 999),
            'georef_origen_lat'       => -33.4489,
            'georef_origen_lng'       => -70.6693,
            'georef_destino_lat'      => -33.5000,
            'georef_destino_lng'      => -70.7000,
        ];
    }
}
