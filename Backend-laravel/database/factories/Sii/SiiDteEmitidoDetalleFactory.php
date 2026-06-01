<?php

namespace Database\Factories\Sii;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiiDteEmitidoDetalle>
 */
class SiiDteEmitidoDetalleFactory extends Factory
{
    protected $model = SiiDteEmitidoDetalle::class;

    public function definition(): array
    {
        $cantidad = $this->faker->randomFloat(2, 1, 100);
        $precio   = $this->faker->randomFloat(2, 100, 100_000);
        $total    = round($cantidad * $precio, 2);

        return [
            'dte_emitido_id'  => SiiDteEmitido::factory(),
            'numero_linea'    => 1, // los tests con varios detalles deben pasar este override
            'nombre_item'     => $this->faker->sentence(3),
            'cantidad'        => $cantidad,
            'unidad_medida'   => 'UN',
            'precio_unitario' => $precio,
            'descuento_pct'   => 0,
            'descuento_monto' => 0,
            'recargo_pct'     => 0,
            'recargo_monto'   => 0,
            'exento'          => false,
            'monto_item'      => $total,
        ];
    }
}
