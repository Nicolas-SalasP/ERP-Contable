<?php

namespace Tests\Unit;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Support\ModuloPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ModuloPermisosPlanTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_alertas_no_se_oculta_por_un_plan_antiguo_sin_ese_module_key(): void
    {
        $empresa = Empresa::create(['rut' => '11.111.111-1', 'razon_social' => 'Plan Viejo SpA']);

        $usuario = $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['dashboard', 'clientes'],
        ]);

        $permisos = ModuloPermisos::permisosUsuario($usuario);

        $this->assertContains('alertas.ver', $permisos);
    }

    public function test_plan_sigue_limitando_modulos_que_no_estan_siempre_disponibles(): void
    {
        $empresa = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Plan Limitado SpA']);

        $usuario = $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['dashboard'],
        ]);

        $permisos = ModuloPermisos::permisosUsuario($usuario);

        $this->assertNotContains('clientes.ver', $permisos);
    }
}
