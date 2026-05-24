<?php

namespace Database\Factories\Sii;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoImpuestoAdicional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiiDteEmitidoImpuestoAdicional>
 */
class SiiDteEmitidoImpuestoAdicionalFactory extends Factory
{
    protected $model = SiiDteEmitidoImpuestoAdicional::class;

    public function definition(): array
    {
        return [
            'dte_emitido_id'         => SiiDteEmitido::factory(),
            'dte_emitido_detalle_id' => null,
            'codigo_impuesto'        => 23, // ILA vinos por defecto
            'tasa'                   => 20.50,
            'monto'                  => 2050,
        ];
    }
}
