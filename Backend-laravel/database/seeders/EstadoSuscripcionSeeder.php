<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\EstadoSuscripcion;

class EstadoSuscripcionSeeder extends Seeder
{
    public function run(): void
    {
        $estados = ['Activa', 'Inactiva', 'Prueba', 'Suspendida'];
        foreach ($estados as $estado) {
            EstadoSuscripcion::firstOrCreate(['nombre' => $estado]);
        }
    }
}