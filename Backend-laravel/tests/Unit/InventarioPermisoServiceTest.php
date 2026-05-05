<?php

namespace Tests\Unit;

use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Services\InventarioPermisoService;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\PreparaInventarioTest;

class InventarioPermisoServiceTest extends TestCase


{
    public function test_administrador_puede_ejecutar_cualquier_permiso(): void
    {
        $usuario = new User([
            'rol_id' => 1,
        ]);

        $usuario->setRelation('rol', new Rol([
            'nombre' => 'Administrador',
            'permisos' => [],
        ]));

        $service = new InventarioPermisoService();

        $service->exigir($usuario, 'inventario.productos.crear');

        $this->assertTrue(true);
    }

    public function test_usuario_con_permiso_puede_ejecutar_operacion(): void
    {
        $usuario = new User([
            'rol_id' => 2,
        ]);

        $usuario->setRelation('rol', new Rol([
            'nombre' => 'Contador',
            'permisos' => [
                'inventario.productos.ver',
                'inventario.productos.crear',
            ],
        ]));

        $service = new InventarioPermisoService();

        $service->exigir($usuario, 'inventario.productos.crear');

        $this->assertTrue(true);
    }

    public function test_usuario_sin_permiso_no_puede_ejecutar_operacion(): void
    {
        $this->expectException(Exception::class);

        $usuario = new User([
            'rol_id' => 3,
        ]);

        $usuario->setRelation('rol', new Rol([
            'nombre' => 'Auditor',
            'permisos' => [
                'inventario.productos.ver',
            ],
        ]));

        $service = new InventarioPermisoService();

        $service->exigir($usuario, 'inventario.productos.crear');
    }

    public function test_permisos_en_formato_json_string_son_soportados(): void
    {
        $usuario = new User([
            'rol_id' => 2,
        ]);

        $rol = new Rol([
            'nombre' => 'Contador',
        ]);

        $rol->permisos = json_encode([
            'inventario.productos.ver',
            'inventario.productos.crear',
        ]);

        $usuario->setRelation('rol', $rol);

        $service = new InventarioPermisoService();

        $service->exigir($usuario, 'inventario.productos.crear');

        $this->assertTrue(true);
    }
}