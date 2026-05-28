<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Models\SiiCaf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCafControllerShowTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_show_retorna_caf_de_la_empresa_propia(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->tipo33()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->getJson("/api/sii/caf/{$caf->id}")
            ->assertStatus(200)
            ->assertJson([
                'id'         => $caf->id,
                'tipo_dte'   => 33,
                'estado'     => 'activo',
            ]);
    }

    public function test_show_oculta_material_criptografico(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $caf = SiiCaf::factory()->create(['empresa_id' => $empresa->id]);

        Sanctum::actingAs($usuario);

        $this->getJson("/api/sii/caf/{$caf->id}")
            ->assertStatus(200)
            ->assertJsonMissing(['rsa_sk_cifrada'])
            ->assertJsonMissing(['xml_completo_cifrado']);
    }

    public function test_show_retorna_404_si_caf_no_existe(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/caf/99999')->assertStatus(404);
    }

    public function test_show_retorna_404_si_caf_es_de_otra_empresa_idor(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB]            = $this->crearEmpresaConAdmin();

        $cafDeB = SiiCaf::factory()->create(['empresa_id' => $empresaB->id]);

        Sanctum::actingAs($usuarioA);

        // 404 (no 403): no revelamos existencia de recursos de otras empresas.
        $this->getJson("/api/sii/caf/{$cafDeB->id}")->assertStatus(404);
    }

    public function test_show_no_resuelve_saldos_como_id(): void
    {
        // Verifica que /saldos no se confunde con /{id} a nivel de routing.
        [, $usuario] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuario);

        $this->getJson('/api/sii/caf/saldos')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }
}
