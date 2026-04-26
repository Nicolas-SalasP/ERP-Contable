<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@tenri.cl'],
            [
                'nombre' => 'Nicolas Salas',
                'password' => Hash::make('password123'),
                'empresa_id' => 1,
                'rol_id' => 1,
                'estado_suscripcion_id' => 1
            ]
        );
    }
}