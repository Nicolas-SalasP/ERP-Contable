<?php

namespace Database\Factories\Sii;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiiDteEmitidoTraslado>
 */
class SiiDteEmitidoTrasladoFactory extends Factory
{
    protected $model = SiiDteEmitidoTraslado::class;

    public function definition(): array
    {
        return [
            'dte_emitido_id'      => SiiDteEmitido::factory()->guiaDespacho(),
            'indicador_traslado'  => SiiDteEmitidoTraslado::IND_OPERACION_CONSTITUYE_VENTA,
            'rut_chofer'          => '11111111-1',
            'nombre_chofer'       => $this->faker->name(),
            'patente'             => strtoupper($this->faker->bothify('??##??')),
            'direccion_destino'   => $this->faker->streetAddress(),
            'comuna_destino'      => 'Santiago',
            'ciudad_destino'      => 'Santiago',
        ];
    }
}
