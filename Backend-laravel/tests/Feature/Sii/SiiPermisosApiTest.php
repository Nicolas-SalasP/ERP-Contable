<?php

namespace Tests\Feature\Sii;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiPermisosApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_configuracion_requiere_autenticacion(): void
    {
        $this->getJson('/api/sii/configuracion')
            ->assertStatus(401);
    }

    public function test_configuracion_rechaza_usuario_sin_permiso_sii(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $usuario = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/configuracion')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_configuracion_permite_usuario_con_permiso_sii(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $this->rolContador->update([
            'permisos' => ['sii.configuracion.ver'],
        ]);
        $usuario = $this->crearUsuario($empresa, $this->rolContador);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/configuracion')
            ->assertStatus(200);
    }

    public function test_configuracion_permite_superadmin_por_jerarquia(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $usuario = $this->crearUsuario($empresa, $this->rolSuperAdmin);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/configuracion')
            ->assertStatus(200);
    }
}
