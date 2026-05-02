<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Core\Models\Rol;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'nombre' => 'Super Admin',
                'jerarquia' => 100,
                'permisos' => [
                    'ventas.ver', 'ventas.crear', 'clientes.ver', 'clientes.crear',
                    'compras.ver', 'compras.crear', 'proveedores.ver', 'proveedores.crear',
                    'tesoreria.ver', 'tesoreria.crear', 'contabilidad.ver', 'contabilidad.crear',
                    'activos.ver', 'activos.crear', 'tributario.ver', 'tributario.crear',
                    'usuarios.ver', 'usuarios.gestionar'
                ]
            ],
            [
                'id' => 2,
                'nombre' => 'Administrador',
                'jerarquia' => 80,
                'permisos' => [
                    'ventas.ver', 'ventas.crear', 'clientes.ver', 'clientes.crear',
                    'compras.ver', 'compras.crear', 'proveedores.ver', 'proveedores.crear',
                    'tesoreria.ver', 'tesoreria.crear', 'contabilidad.ver', 'contabilidad.crear',
                    'activos.ver', 'activos.crear', 'tributario.ver', 'tributario.crear'
                ]
            ],
            [
                'id' => 3,
                'nombre' => 'Contador',
                'jerarquia' => 50,
                'permisos' => [
                    'tesoreria.ver', 'tesoreria.crear', 'contabilidad.ver', 'contabilidad.crear',
                    'tributario.ver', 'tributario.crear', 'activos.ver'
                ]
            ],
            [
                'id' => 4,
                'nombre' => 'Auditor',
                'jerarquia' => 50,
                'permisos' => [
                    'ventas.ver', 'clientes.ver', 'compras.ver', 'proveedores.ver',
                    'tesoreria.ver', 'contabilidad.ver', 'activos.ver', 'tributario.ver',
                    'usuarios.ver'
                ]
            ]
        ];

        foreach ($roles as $rol) {
            Rol::updateOrCreate(['id' => $rol['id']], $rol);
        }
    }
}