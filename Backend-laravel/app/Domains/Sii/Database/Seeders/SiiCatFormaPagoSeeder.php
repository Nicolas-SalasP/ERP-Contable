<?php

namespace App\Domains\Sii\Database\Seeders;

use App\Domains\Sii\Models\Catalogos\FormaPagoSii;
use Illuminate\Database\Seeder;

class SiiCatFormaPagoSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            [
                'codigo'      => FormaPagoSii::CONTADO,
                'nombre'      => 'Contado',
                'descripcion' => 'Pago al contado',
            ],
            [
                'codigo'      => FormaPagoSii::CREDITO,
                'nombre'      => 'Crédito',
                'descripcion' => 'Pago a crédito o con plazo',
            ],
            [
                'codigo'      => FormaPagoSii::SIN_COSTO,
                'nombre'      => 'Sin Costo',
                'descripcion' => 'Servicio o producto gratuito',
            ],
        ];

        foreach ($registros as $registro) {
            FormaPagoSii::updateOrCreate(
                ['codigo' => $registro['codigo']],
                $registro
            );
        }
    }
}
