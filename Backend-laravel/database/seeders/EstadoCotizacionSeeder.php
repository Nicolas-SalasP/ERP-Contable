<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Comercial\Models\EstadoCotizacion;

class EstadoCotizacionSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['nombre' => 'Borrador', 'descripcion' => 'En edición, no enviada'],
            ['nombre' => 'Enviada', 'descripcion' => 'Enviada al cliente'],
            ['nombre' => 'Aceptada', 'descripcion' => 'Aprobada para facturar'],
            ['nombre' => 'Rechazada', 'descripcion' => 'Rechazada por el cliente'],
            ['nombre' => 'Expirada', 'descripcion' => 'Fuera de plazo de validez']
        ];

        foreach ($estados as $estado) {
            EstadoCotizacion::firstOrCreate(['nombre' => $estado['nombre']], ['descripcion' => $estado['descripcion']]);
        }
    }
}