<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\Empresa;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::firstOrCreate(
            ['rut' => '77.777.777-7'],
            [
                'razon_social' => 'Tenri SpA',
                'email' => 'contacto@tenri.cl',
                'direccion' => 'Pudahuel, Región Metropolitana',
                'color_primario' => '#10b981',
                'regimen_tributario' => '14_D3',
                'tasa_impuesto' => 25.00
            ]
        );
    }
}