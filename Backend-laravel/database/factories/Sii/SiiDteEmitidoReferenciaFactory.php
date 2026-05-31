<?php

namespace Database\Factories\Sii;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoReferencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiiDteEmitidoReferencia>
 */
class SiiDteEmitidoReferenciaFactory extends Factory
{
    protected $model = SiiDteEmitidoReferencia::class;

    public function definition(): array
    {
        return [
            'dte_emitido_id'            => SiiDteEmitido::factory()->notaCredito(),
            'numero_linea'              => 1,
            'tipo_documento_referencia' => '33',
            'folio_referencia'          => (string) random_int(1, 999_999),
            'fecha_referencia'          => $this->faker->dateTimeBetween('-60 days', '-1 day')->format('Y-m-d'),
            'codigo_referencia'         => SiiDteEmitidoReferencia::CODIGO_ANULA,
            'razon_referencia'          => 'Anula factura original',
        ];
    }
}
