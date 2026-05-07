<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\Rol;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Administrador', 'Contador', 'Ventas', 'Auditor'];
        foreach ($roles as $rol) {
            Rol::firstOrCreate(['nombre' => $rol]);
        }
    }
}