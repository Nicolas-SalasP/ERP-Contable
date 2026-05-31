<?php

namespace Database\Factories\Sii;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiiDteEmitido>
 */
class SiiDteEmitidoFactory extends Factory
{
    protected $model = SiiDteEmitido::class;

    public function definition(): array
    {
        $neto  = $this->faker->numberBetween(1000, 1_000_000);
        $iva   = (int) round($neto * 0.19);
        $total = $neto + $iva;

        return [
            // Empresa creada on-the-fly (modelo Empresa no usa HasFactory).
            'empresa_id'          => fn () => $this->crearEmpresaStub()->id,
            'tipo_dte'            => SiiDteEmitido::TIPO_FACTURA,
            'folio'               => random_int(1, 999_999),
            'fecha_emision'       => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'emisor_rut'          => $this->rutValido(76_000_000, 99_999_999),
            'emisor_razon_social' => $this->faker->company(),
            'receptor_rut'        => $this->rutValido(11_000_000, 75_999_999),
            'receptor_razon_social' => $this->faker->company(),
            'moneda'              => 'CLP',
            'monto_neto'          => $neto,
            'monto_exento'        => 0,
            'tasa_iva'            => 19.00,
            'iva'                 => $iva,
            'monto_total'         => $total,
            'estado'              => SiiDteEmitido::ESTADO_BORRADOR,
            'es_cedible'          => true,
        ];
    }

    public function factura(): static
    {
        return $this->state(fn () => ['tipo_dte' => SiiDteEmitido::TIPO_FACTURA]);
    }

    public function boleta(): static
    {
        return $this->state(fn () => ['tipo_dte' => SiiDteEmitido::TIPO_BOLETA]);
    }

    public function notaCredito(): static
    {
        return $this->state(fn () => ['tipo_dte' => SiiDteEmitido::TIPO_NOTA_CREDITO]);
    }

    public function guiaDespacho(): static
    {
        return $this->state(fn () => ['tipo_dte' => SiiDteEmitido::TIPO_GUIA_DESPACHO]);
    }

    public function aceptado(): static
    {
        return $this->state(fn () => [
            'estado'               => SiiDteEmitido::ESTADO_ACEPTADO,
            'track_id'             => (string) random_int(10_000_000_000, 99_999_999_999),
            'fecha_aceptacion_sii' => now(),
        ]);
    }

    public function rechazado(): static
    {
        return $this->state(fn () => [
            'estado'               => SiiDteEmitido::ESTADO_RECHAZADO,
            'codigo_respuesta_sii' => 'RCH',
            'glosa_sii'            => 'Glosa de prueba: rechazado por simulacion.',
            'fecha_rechazo_sii'    => now(),
        ]);
    }

    private function crearEmpresaStub(): Empresa
    {
        $num = random_int(76_000_000, 99_999_999);
        $dv  = RutHelper::calcularDv($num);

        return Empresa::create([
            'rut'          => $num . '-' . $dv,
            'razon_social' => 'Empresa Factory ' . uniqid(),
        ]);
    }

    private function rutValido(int $min, int $max): string
    {
        $num = random_int($min, $max);
        $dv  = RutHelper::calcularDv($num);

        return $num . '-' . $dv;
    }
}
