<?php

namespace Tests\Feature\Alertas;

use App\Domains\Alertas\Models\Alerta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class AlertaControllerTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_lista_solo_alertas_pendientes_de_la_empresa_activa(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        Alerta::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'tipo' => 'cxc_vencida',
            'nivel' => Alerta::NIVEL_ADVERTENCIA,
            'mensaje' => 'Factura vencida de prueba',
            'estado' => Alerta::ESTADO_ENVIADA,
        ]);

        Alerta::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'tipo' => 'cxc_vencida',
            'nivel' => Alerta::NIVEL_INFO,
            'mensaje' => 'Ya resuelta',
            'estado' => Alerta::ESTADO_RESUELTA,
        ]);

        $response = $this->actingAs($usuario)->getJson('/api/alertas');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_resolver_marca_la_alerta_como_resuelta(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $alerta = Alerta::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'tipo' => 'periodo_sin_cerrar',
            'nivel' => Alerta::NIVEL_INFO,
            'mensaje' => 'Periodo sin cerrar de prueba',
            'estado' => Alerta::ESTADO_ENVIADA,
        ]);

        $response = $this->actingAs($usuario)
            ->patchJson("/api/alertas/{$alerta->id}", ['estado' => 'resuelta']);

        $response->assertStatus(200);
        $this->assertSame('resuelta', $alerta->fresh()->estado);
        $this->assertSame($usuario->id, $alerta->fresh()->resuelta_por);
    }

    public function test_una_empresa_no_puede_resolver_alerta_de_otra_empresa(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin();
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin();

        $alertaDeA = Alerta::withoutGlobalScopes()->create([
            'empresa_id' => $empresaA->id,
            'tipo' => 'cxc_vencida',
            'nivel' => Alerta::NIVEL_INFO,
            'mensaje' => 'Alerta de otra empresa',
            'estado' => Alerta::ESTADO_ENVIADA,
        ]);

        $response = $this->actingAs($usuarioB)
            ->patchJson("/api/alertas/{$alertaDeA->id}", ['estado' => 'resuelta']);

        $response->assertStatus(404);
        $this->assertSame('enviada', $alertaDeA->fresh()->estado);
    }

    public function test_sin_permiso_alertas_ver_retorna_403(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $usuarioSinPermiso = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $this->actingAs($usuarioSinPermiso)
            ->getJson('/api/alertas')
            ->assertStatus(403);
    }
}
