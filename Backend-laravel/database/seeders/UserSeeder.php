<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $empresaId = 1;
        $password = Hash::make('password123');

        $usuarios = [
            [
                'nombre' => 'Dueño Super Admin',
                'email' => 'superadmin@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 1 // Super Admin
            ],
            [
                'nombre' => 'Gerente Administrador',
                'email' => 'admin@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 2 // Administrador
            ],
            [
                'nombre' => 'Experto Contador',
                'email' => 'contador@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 3 // Contador
            ],
            [
                'nombre' => 'Revisor Auditor',
                'email' => 'auditor@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 4 // Auditor
            ]
        ];

        foreach ($usuarios as $usuario) {
            User::firstOrCreate(['email' => $usuario['email']], $usuario);
        }
    }
}