<?php

namespace Database\Factories\Sii;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiCafFolioUso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiiCafFolioUso>
 */
class SiiCafFolioUsoFactory extends Factory
{
    protected $model = SiiCafFolioUso::class;

    public function definition(): array
    {
        return [
            'caf_id'             => SiiCaf::factory(),
            'folio'              => 1,
            'dte_emitido_id'     => null,
            'estado'             => SiiCafFolioUso::ESTADO_RESERVADO,
            'reservado_at'       => now(),
            'usado_at'           => null,
            'liberado_at'        => null,
            'razon_liberacion'   => null,
            'usuario_reservo_id' => null,
        ];
    }

    public function reservado(): static
    {
        return $this->state(fn () => [
            'estado'       => SiiCafFolioUso::ESTADO_RESERVADO,
            'reservado_at' => now(),
            'usado_at'     => null,
            'liberado_at'  => null,
        ]);
    }

    public function usado(): static
    {
        return $this->state(fn () => [
            'estado'       => SiiCafFolioUso::ESTADO_USADO,
            'reservado_at' => now()->subMinutes(10),
            'usado_at'     => now(),
        ]);
    }

    public function huerfano(): static
    {
        return $this->state(fn () => [
            'estado'           => SiiCafFolioUso::ESTADO_HUERFANO,
            'reservado_at'     => now()->subDays(2),
            'liberado_at'      => now(),
            'razon_liberacion' => 'Abortado por test',
        ]);
    }
}
