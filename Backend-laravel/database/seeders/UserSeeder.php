<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $empresaId = 1;

        // No hardcodear credenciales en el repo. Se toma de DEMO_SEED_PASSWORD; si no
        // esta definida, se genera una aleatoria y se informa por consola (la conoce
        // solo quien corre el seed). En CI debe inyectarse via secret.
        $plainPassword = env('DEMO_SEED_PASSWORD');
        if (empty($plainPassword)) {
            $plainPassword = Str::password(16);
            $this->command?->warn(
                "UserSeeder: DEMO_SEED_PASSWORD no definido. Password generado para usuarios demo: {$plainPassword}"
            );
        }

        $password = Hash::make($plainPassword);
        $estadoActivo = 1;

        $usuarios = [
            [
                'nombre' => 'Dueño Super Admin',
                'email' => 'superadmin@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 1,
                'estado_suscripcion_id' => $estadoActivo
            ],
            [
                'nombre' => 'Gerente Administrador',
                'email' => 'admin@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 2,
                'estado_suscripcion_id' => $estadoActivo
            ],
            [
                'nombre' => 'Experto Contador',
                'email' => 'contador@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 3,
                'estado_suscripcion_id' => $estadoActivo
            ],
            [
                'nombre' => 'Revisor Auditor',
                'email' => 'auditor@tenri.cl',
                'password' => $password,
                'empresa_id' => $empresaId,
                'rol_id' => 4,
                'estado_suscripcion_id' => $estadoActivo
            ]
        ];

        foreach ($usuarios as $usuario) {
            User::updateOrCreate(
                ['email' => $usuario['email']], 
                $usuario
            );
        }
    }
}